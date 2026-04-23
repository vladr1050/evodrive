package teltonika

import (
	"encoding/hex"
	"testing"
)

// Teltonika wiki / community example for "getinfo".
func TestEncodeCodec12Command_getinfoGolden(t *testing.T) {
	want, err := hex.DecodeString("000000000000000F0C010500000007676574696E666F0100004312")
	if err != nil {
		t.Fatal(err)
	}
	b, err := EncodeCodec12Command("getinfo")
	if err != nil {
		t.Fatal(err)
	}
	if len(b) != len(want) {
		t.Fatalf("len got %d want %d\ngot %x", len(b), len(want), b)
	}
	for i := range want {
		if b[i] != want[i] {
			t.Fatalf("diff at %d: got %x want %x", i, b, want)
		}
	}
}
