package gateway

import (
	"encoding/binary"
	"io"
	"log"
	"net"
	"sync"
	"sync/atomic"
	"time"

	"evodrive/teltonika-gateway/internal/teltonika"
)

// Session is one FMC TCP connection after IMEI handshake.
type Session struct {
	Conn net.Conn
	IMEI string

	manager *Manager

	// writeMu serializes Codec8 ACK and Codec12 command writes (same host→device direction).
	// Reads use the Conn without this lock; TCP full-duplex keeps device→server bytes separate.
	writeMu sync.Mutex
	cmdMu   sync.Mutex

	lastSeen atomic.Int64 // unix nano

	respChMu sync.Mutex
	respCh   chan string // capacity 1; next Codec12 response text
}

func NewSession(conn net.Conn, imei string, mgr *Manager) *Session {
	s := &Session{Conn: conn, IMEI: imei, manager: mgr}
	s.touch()
	return s
}

func (s *Session) touch() {
	s.lastSeen.Store(time.Now().UnixNano())
}

func (s *Session) LastSeen() time.Time {
	return time.Unix(0, s.lastSeen.Load())
}

func (s *Session) Close() error {
	return s.Conn.Close()
}

// Run reads device frames until disconnect. Must be started as a goroutine.
func (s *Session) Run() {
	defer s.manager.Remove(s)
	defer func() { _ = s.Conn.Close() }()

	for {
		_ = s.Conn.SetReadDeadline(time.Now().Add(12 * time.Hour))
		frame, err := teltonika.ReadFrame(s.Conn)
		if err != nil {
			if err != io.EOF {
				log.Printf("imei=%s read frame: %v", s.IMEI, err)
			}
			return
		}
		s.touch()
		payload := teltonika.Payload(frame)
		if len(payload) == 0 {
			continue
		}
		switch payload[0] {
		case teltonika.Codec8, teltonika.Codec8E, teltonika.Codec16:
			n := teltonika.Codec8AckCount(payload)
			var ack [4]byte
			binary.BigEndian.PutUint32(ack[:], n)
			s.writeMu.Lock()
			_, werr := s.Conn.Write(ack[:])
			s.writeMu.Unlock()
			if werr != nil {
				log.Printf("imei=%s ack: %v", s.IMEI, werr)
				return
			}
		case teltonika.Codec12:
			txt, err := teltonika.Codec12ResponsePayload(payload)
			if err != nil {
				log.Printf("imei=%s codec12 parse: %v", s.IMEI, err)
				continue
			}
			s.respChMu.Lock()
			ch := s.respCh
			s.respChMu.Unlock()
			if ch != nil {
				select {
				case ch <- txt:
				default:
				}
			}
		default:
			log.Printf("imei=%s unknown codec %02x len=%d", s.IMEI, payload[0], len(payload))
		}
	}
}

// SendCodec12Command sends one Codec12 command and waits for a Codec12 response (0x06) or timeout.
func (s *Session) SendCodec12Command(command string, timeout time.Duration) (response string, err error) {
	s.cmdMu.Lock()
	defer s.cmdMu.Unlock()

	ch := make(chan string, 1)
	s.respChMu.Lock()
	s.respCh = ch
	s.respChMu.Unlock()
	defer func() {
		s.respChMu.Lock()
		if s.respCh == ch {
			s.respCh = nil
		}
		s.respChMu.Unlock()
	}()

	pkt, err := teltonika.EncodeCodec12Command(command)
	if err != nil {
		return "", err
	}
	s.writeMu.Lock()
	_, werr := s.Conn.Write(pkt)
	s.writeMu.Unlock()
	if werr != nil {
		return "", werr
	}

	timer := time.NewTimer(timeout)
	defer timer.Stop()
	select {
	case resp := <-ch:
		return resp, nil
	case <-timer.C:
		return "", ErrCommandTimeout
	}
}
