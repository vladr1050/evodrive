package teltonika

import (
	"encoding/binary"
	"fmt"
	"io"
)

const maxDataFieldBytes = 16 << 20 // 16 MiB safety cap

// ReadFrame reads one Teltonika TCP AVL frame and normalizes it to:
// 4-byte preamble (zeros) + 4-byte big-endian data length + payload (+ optional Codec12 CRC trailer).
//
// Teltonika documents a 4-zero preamble before the length field; some firmware (e.g. certain FMB/FMC
// builds or duplicate-server links) sends **only** length + payload with no leading zeros. Both are supported.
func ReadFrame(r io.Reader) ([]byte, error) {
	var first [4]byte
	if _, err := io.ReadFull(r, first[:]); err != nil {
		return nil, err
	}

	var preamble [4]byte
	var dataLen uint32

	if first == [4]byte{0, 0, 0, 0} {
		preamble = first
		if err := binary.Read(r, binary.BigEndian, &dataLen); err != nil {
			return nil, err
		}
	} else {
		// No leading zero preamble: first 4 bytes are the data-field length (big-endian).
		preamble = [4]byte{0, 0, 0, 0}
		dataLen = binary.BigEndian.Uint32(first[:])
	}

	if dataLen == 0 || dataLen > maxDataFieldBytes {
		return nil, fmt.Errorf("invalid data length %d", dataLen)
	}
	payload := make([]byte, int(dataLen))
	if _, err := io.ReadFull(r, payload); err != nil {
		return nil, err
	}
	// Codec12: data length excludes trailing 4-byte CRC (same framing as outbound commands).
	if len(payload) > 0 && payload[0] == Codec12 {
		var crcTrailer [4]byte
		if _, err := io.ReadFull(r, crcTrailer[:]); err != nil {
			return nil, err
		}
		payload = append(payload, crcTrailer[:]...)
	}
	out := make([]byte, 8+len(payload))
	copy(out[0:4], preamble[:])
	binary.BigEndian.PutUint32(out[4:8], dataLen)
	copy(out[8:], payload)
	return out, nil
}

// Payload returns the data field (after preamble + length) from a full frame.
func Payload(frame []byte) []byte {
	if len(frame) < 8 {
		return nil
	}
	return frame[8:]
}

// Codec8FirstByte / Codec12FirstByte identify common codecs.
const (
	Codec8  = 0x08
	Codec8E = 0x8E
	Codec12 = 0x0C
)

// Codec8AckCount returns Number of Data 1 (naive single-batch assumption).
func Codec8AckCount(payload []byte) uint32 {
	if len(payload) < 2 {
		return 0
	}
	return uint32(payload[1])
}
