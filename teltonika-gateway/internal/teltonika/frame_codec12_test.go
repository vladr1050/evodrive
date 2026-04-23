package teltonika

import (
	"bytes"
	"encoding/binary"
	"io"
	"testing"
)

func TestReadFrame_codec12AppendsCRCtrailer(t *testing.T) {
	// Minimal Codec12 response: inner (codec..qty2) then CRC; dataLen = len(inner) only.
	resp := []byte("OK")
	innerLen := 1 + 1 + 1 + 4 + len(resp) + 1
	inner := make([]byte, 0, innerLen)
	inner = append(inner, codec12ID, codec12ResponseQty, codec12TypeResponse)
	var sz [4]byte
	binary.BigEndian.PutUint32(sz[:], uint32(len(resp)))
	inner = append(inner, sz[:]...)
	inner = append(inner, resp...)
	inner = append(inner, codec12ResponseQty)
	crc := CRC16Teltonika(inner)
	var crcB [4]byte
	binary.BigEndian.PutUint32(crcB[:], uint32(crc))

	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(inner)))
	_, _ = wire.Write(inner)
	_, _ = wire.Write(crcB[:])

	frame, err := ReadFrame(&wire)
	if err != nil {
		t.Fatal(err)
	}
	pl := Payload(frame)
	txt, err := Codec12ResponsePayload(pl)
	if err != nil {
		t.Fatal(err)
	}
	if txt != "OK" {
		t.Fatalf("got %q", txt)
	}
}

func TestReadFrame_codec8NoExtraTrailer(t *testing.T) {
	// Fake Codec8: preamble + len=4 + payload 4 bytes (codec + count + fake crc16)
	pl := []byte{Codec8, 2, 0xAB, 0xCD}
	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(pl)))
	_, _ = wire.Write(pl)

	frame, err := ReadFrame(&wire)
	if err != nil {
		t.Fatal(err)
	}
	if len(Payload(frame)) != len(pl) {
		t.Fatalf("len %d want %d", len(Payload(frame)), len(pl))
	}
	if !bytes.Equal(Payload(frame), pl) {
		t.Fatal("payload mismatch")
	}
	if _, err := wire.ReadByte(); err != io.EOF {
		t.Fatalf("expected EOF, got %v", err)
	}
}
