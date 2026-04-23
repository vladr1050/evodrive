package teltonika

// CRC16Teltonika matches Teltonika Codec12 (poly 0xA001 reversed, init 0x0000).
// See github.com/basvdlei/gotsmart/crc16 (used by teltonikaparser).
func CRC16Teltonika(data []byte) uint16 {
	var crc uint16 = 0x0000
	for _, b := range data {
		crc ^= uint16(b)
		for i := 0; i < 8; i++ {
			if crc&1 != 0 {
				crc = (crc >> 1) ^ 0xA001
			} else {
				crc >>= 1
			}
		}
	}
	return crc
}
