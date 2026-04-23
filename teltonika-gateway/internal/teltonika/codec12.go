package teltonika

import (
	"bytes"
	"encoding/binary"
	"fmt"
)

const (
	codec12ID            = 0x0C
	codec12TypeCommand   = 0x05
	codec12TypeResponse  = 0x06
	codec12CommandQty    = 0x01
	codec12ResponseQty   = 0x01
)

// EncodeCodec12Command builds a full TCP frame: preamble + data length + codec12 + CRC (4 bytes BE).
// Framing matches Teltonika / filipkroca teltonikaparser: data length = bytes from Codec ID through second quantity (excludes trailing CRC).
func EncodeCodec12Command(command string) ([]byte, error) {
	cmd := []byte(command)
	if len(cmd) > 1<<20 {
		return nil, fmt.Errorf("command too long")
	}
	// inner = CodecID + Qty1 + Type + CmdSize(4) + Command + Qty2
	innerLen := 1 + 1 + 1 + 4 + len(cmd) + 1
	dataSize := uint32(innerLen)

	buf := new(bytes.Buffer)
	_, _ = buf.Write([]byte{0, 0, 0, 0}) // preamble
	_ = binary.Write(buf, binary.BigEndian, dataSize)
	_ = buf.WriteByte(codec12ID)
	_ = buf.WriteByte(codec12CommandQty)
	_ = buf.WriteByte(codec12TypeCommand)
	_ = binary.Write(buf, binary.BigEndian, uint32(len(cmd)))
	_, _ = buf.Write(cmd)
	_ = buf.WriteByte(codec12CommandQty)

	raw := buf.Bytes()
	crc := CRC16Teltonika(raw[8:])
	var crcW [4]byte
	binary.BigEndian.PutUint32(crcW[:], uint32(crc))
	_, _ = buf.Write(crcW[:])

	return buf.Bytes(), nil
}

// DecodeCodec12TCPFrame parses a full TCP frame (preamble + 4-byte data size + inner + 4-byte CRC).
func DecodeCodec12TCPFrame(packet []byte) (response string, err error) {
	if len(packet) < 8+1+1+1+4+1+4 {
		return "", fmt.Errorf("packet too short")
	}
	if !bytes.Equal(packet[0:4], []byte{0, 0, 0, 0}) {
		return "", fmt.Errorf("bad preamble")
	}
	dataSize := int(binary.BigEndian.Uint32(packet[4:8]))
	if dataSize < 8 || 8+dataSize+4 > len(packet) {
		return "", fmt.Errorf("invalid data size %d (len %d)", dataSize, len(packet))
	}
	inner := packet[8 : 8+dataSize]
	crcWant := binary.BigEndian.Uint32(packet[8+dataSize : 8+dataSize+4])
	crcGot := uint32(CRC16Teltonika(inner))
	if crcGot != crcWant {
		return "", fmt.Errorf("crc mismatch want %08x got %08x", crcWant, crcGot)
	}
	combined := append(append([]byte{}, inner...), packet[8+dataSize:8+dataSize+4]...)

	return Codec12ResponsePayload(combined)
}

// Codec12ResponsePayload extracts response from a Codec12 data field `body` (starts with 0x0C, includes trailing CRC inside len(body)).
func Codec12ResponsePayload(body []byte) (string, error) {
	if len(body) < 8+4 {
		return "", fmt.Errorf("codec12 body short")
	}
	inner := body[:len(body)-4]
	crcWant := binary.BigEndian.Uint32(body[len(body)-4:])
	if uint32(CRC16Teltonika(inner)) != crcWant {
		return "", fmt.Errorf("codec12 inner crc mismatch")
	}
	if inner[0] != codec12ID || inner[2] != codec12TypeResponse {
		return "", fmt.Errorf("unexpected codec12 message")
	}
	respSize := int(binary.BigEndian.Uint32(inner[3:7]))
	if respSize < 0 || 7+respSize+1 > len(inner) { // + trailing quantity byte
		return "", fmt.Errorf("response overflow")
	}
	return string(inner[7 : 7+respSize]), nil
}
