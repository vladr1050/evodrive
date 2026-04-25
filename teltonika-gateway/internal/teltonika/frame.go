package teltonika

import (
	"encoding/binary"
	"fmt"
	"io"
)

const maxDataFieldBytes = 512 << 10 // 512 KiB — Teltonika AVL packets are typically small; avoids huge ReadFull + stream corruption.

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
	if len(payload) > 0 {
		codec := payload[0]
		if codecHasTCPCRCTrailer(codec) {
			embedded := hasEmbeddedCRC(payload)
			switch codec {
			case Codec12:
				// Codec12 parser expects CRC to be present in payload.
				if !embedded {
					var trailer [4]byte
					if _, err := io.ReadFull(r, trailer[:]); err != nil {
						return nil, err
					}
					payload = append(payload, trailer[:]...)
				}
			default:
				// AVL codecs (8/8E/10) and command codecs (13/14): keep payload normalized without CRC.
				if embedded {
					payload = payload[:len(payload)-4]
				} else {
					var trailer [4]byte
					if _, err := io.ReadFull(r, trailer[:]); err != nil {
						return nil, err
					}
				}
			}
		}
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
	Codec8   = 0x08
	Codec8E  = 0x8E
	Codec16  = 0x10
	Codec12  = 0x0C
	Codec13  = 0x0D
	Codec14  = 0x0E
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
