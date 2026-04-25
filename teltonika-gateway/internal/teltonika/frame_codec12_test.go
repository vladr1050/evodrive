package teltonika

import (
	"bytes"
	"encoding/binary"
	"io"
	"testing"
)

func TestReadFrame_codec12CRCIncludedInDataLength(t *testing.T) {
	// Some firmware sets data-field length to inner+CRC (entire Codec12 block in one chunk).
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
	full := append(inner, crcB[:]...)

	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(full)))
	_, _ = wire.Write(full)

	frame, err := ReadFrame(&wire)
	if err != nil {
		t.Fatal(err)
	}
	txt, err := Codec12ResponsePayload(Payload(frame))
	if err != nil {
		t.Fatal(err)
	}
	if txt != "OK" {
		t.Fatalf("got %q", txt)
	}
	if _, err := wire.ReadByte(); err != io.EOF {
		t.Fatalf("expected EOF (no extra CRC read), got %v", err)
	}
}

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

func TestReadFrame_noPreambleLengthFirst(t *testing.T) {
	// Some devices send: [4-byte BE length][payload] without leading 00 00 00 00.
	pl := []byte{Codec8, 1, 0xAB, 0xCD}
	dataLen := uint32(len(pl))
	var crcB [4]byte
	binary.BigEndian.PutUint32(crcB[:], uint32(CRC16Teltonika(pl)))
	var wire bytes.Buffer
	_ = binary.Write(&wire, binary.BigEndian, dataLen)
	_, _ = wire.Write(pl)
	_, _ = wire.Write(crcB[:])

	frame, err := ReadFrame(&wire)
	if err != nil {
		t.Fatal(err)
	}
	if len(Payload(frame)) != len(pl) {
		t.Fatalf("payload len %d want %d", len(Payload(frame)), len(pl))
	}
	// Normalized frame always starts with zero preamble + same length in bytes 4–7.
	if frame[0] != 0 || frame[1] != 0 || frame[2] != 0 || frame[3] != 0 {
		t.Fatalf("expected normalized preamble, got %x", frame[0:4])
	}
	if binary.BigEndian.Uint32(frame[4:8]) != dataLen {
		t.Fatalf("length mismatch")
	}
}

func TestReadFrame_codec16ConsumesTCPTrailerCRC(t *testing.T) {
	// Codec16 uses the same TCP AVL envelope as Codec8 (Teltonika wiki).
	pl := []byte{Codec16, 1, 0x11, 0x22, 0x33, 0x44}
	var crcB [4]byte
	binary.BigEndian.PutUint32(crcB[:], uint32(CRC16Teltonika(pl)))
	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(pl)))
	_, _ = wire.Write(pl)
	_, _ = wire.Write(crcB[:])

	frame, err := ReadFrame(&wire)
	if err != nil {
		t.Fatal(err)
	}
	if len(Payload(frame)) != len(pl) {
		t.Fatalf("len %d want %d", len(Payload(frame)), len(pl))
	}
	if Payload(frame)[0] != Codec16 {
		t.Fatalf("codec %02x", Payload(frame)[0])
	}
	if _, err := wire.ReadByte(); err != io.EOF {
		t.Fatalf("expected EOF, got %v", err)
	}
}

func TestReadFrame_codec8ConsumesTCPTrailerCRC(t *testing.T) {
	// Codec8 TCP: data field then 4-byte CRC (Teltonika wiki); payload returned excludes CRC.
	pl := []byte{Codec8, 2, 0xAB, 0xCD}
	var crcB [4]byte
	binary.BigEndian.PutUint32(crcB[:], uint32(CRC16Teltonika(pl)))
	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(pl)))
	_, _ = wire.Write(pl)
	_, _ = wire.Write(crcB[:])

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

func TestReadFrame_codec8CRCIncludedInDataLength(t *testing.T) {
	// Variant: dataLen already includes trailing CRC bytes.
	pl := []byte{Codec8, 2, 0xAB, 0xCD}
	var crcB [4]byte
	binary.BigEndian.PutUint32(crcB[:], uint32(CRC16Teltonika(pl)))
	full := append(append([]byte{}, pl...), crcB[:]...)

	var wire bytes.Buffer
	_, _ = wire.Write([]byte{0, 0, 0, 0})
	_ = binary.Write(&wire, binary.BigEndian, uint32(len(full)))
	_, _ = wire.Write(full)

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
		t.Fatalf("expected EOF (no extra trailer read), got %v", err)
	}
}
