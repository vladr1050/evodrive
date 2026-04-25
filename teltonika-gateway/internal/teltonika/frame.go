package teltonika

import (
	"bufio"
	"bytes"
	"encoding/binary"
	"fmt"
	"io"
)

const maxDataFieldBytes = 512 << 10 // 512 KiB — Teltonika AVL packets are typically small; avoids huge ReadFull + stream corruption.

// ReadFrame reads one Teltonika TCP AVL frame and normalizes it to:
// 4-byte preamble (zeros) + 4-byte big-endian data length + payload (+ optional CRC handling per codec).
//
// Teltonika documents a 4-zero preamble before the length field; some firmware sends **only**
// length + payload with no leading zeros. Both are supported.
//
// If the stream is temporarily misaligned (wrong length / garbage), ReadFrame skips up to
// maxResyncSkipBytes one-byte steps and re-peeks until a plausible header appears — this avoids
// dropping the TCP session on values like 0xFECAFE00 or 0xFFFFFFFF.
func ReadFrame(br *bufio.Reader) ([]byte, error) {
	const maxResyncSkipBytes = 4096
	skipped := 0

	for {
		peek, err := br.Peek(8)
		if err != nil {
			return nil, err
		}
		if len(peek) < 8 {
			return nil, io.ErrUnexpectedEOF
		}

		var dataLen uint32
		headerBytes := 0

		if bytes.Equal(peek[0:4], []byte{0, 0, 0, 0}) {
			dataLen = binary.BigEndian.Uint32(peek[4:8])
			if dataLen > 0 && dataLen <= maxDataFieldBytes {
				headerBytes = 8
			}
		} else {
			dataLen = binary.BigEndian.Uint32(peek[0:4])
			if dataLen > 0 && dataLen <= maxDataFieldBytes {
				headerBytes = 4
			}
		}

		if headerBytes == 0 {
			if skipped >= maxResyncSkipBytes {
				return nil, fmt.Errorf("could not resync teltonika tcp stream within %d byte skips", maxResyncSkipBytes)
			}
			if _, err := br.Discard(1); err != nil {
				return nil, err
			}
			skipped++
			continue
		}

		if _, err := br.Discard(headerBytes); err != nil {
			return nil, err
		}

		payload := make([]byte, int(dataLen))
		if _, err := io.ReadFull(br, payload); err != nil {
			return nil, err
		}

		payload, err = consumeCodecTCPTrailer(br, payload)
		if err != nil {
			return nil, err
		}

		out := make([]byte, 8+len(payload))
		copy(out[0:4], []byte{0, 0, 0, 0})
		binary.BigEndian.PutUint32(out[4:8], dataLen)
		copy(out[8:], payload)
		return out, nil
	}
}

// Payload returns the data field (after preamble + length) from a full frame.
func Payload(frame []byte) []byte {
	if len(frame) < 8 {
		return nil
	}
	return frame[8:]
}

const (
	Codec8  = 0x08
	Codec8E = 0x8E
	Codec16 = 0x10
	Codec12 = 0x0C
	Codec13 = 0x0D
	Codec14 = 0x0E
)

// Codec8AckCount returns Number of Data 1 (naive single-batch assumption).
func Codec8AckCount(payload []byte) uint32 {
	if len(payload) < 2 {
		return 0
	}
	return uint32(payload[1])
}

func hasEmbeddedCRC(payload []byte) bool {
	if len(payload) < 6 { // codec + at least one byte + 4-byte CRC trailer
		return false
	}
	inner := payload[:len(payload)-4]
	crcWant := binary.BigEndian.Uint32(payload[len(payload)-4:])
	return uint32(CRC16Teltonika(inner)) == crcWant
}

func codecHasTCPCRCTrailer(codec byte) bool {
	switch codec {
	case Codec8, Codec8E, Codec16, Codec12, Codec13, Codec14:
		return true
	default:
		return false
	}
}

func consumeCodecTCPTrailer(br *bufio.Reader, payload []byte) ([]byte, error) {
	if len(payload) == 0 {
		return payload, nil
	}
	codec := payload[0]
	if !codecHasTCPCRCTrailer(codec) {
		return payload, nil
	}

	embedded := hasEmbeddedCRC(payload)
	switch codec {
	case Codec12:
		if !embedded {
			var trailer [4]byte
			if _, err := io.ReadFull(br, trailer[:]); err != nil {
				return nil, err
			}
			payload = append(payload, trailer[:]...)
		}
	default:
		if embedded {
			payload = payload[:len(payload)-4]
		} else {
			var trailer [4]byte
			if _, err := io.ReadFull(br, trailer[:]); err != nil {
				return nil, err
			}
		}
	}
	return payload, nil
}
