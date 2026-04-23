package gateway

import (
	"encoding/binary"
	"fmt"
	"io"
	"log"
	"net"
)

const imeiMaxLen = 32

// ServeTCP accepts Teltonika device connections: IMEI handshake, then session loop.
func ServeTCP(addr string, mgr *Manager) error {
	ln, err := net.Listen("tcp", addr)
	if err != nil {
		return err
	}
	log.Printf("teltonika tcp listening on %s", addr)
	for {
		conn, err := ln.Accept()
		if err != nil {
			log.Printf("accept: %v", err)
			continue
		}
		go handleDeviceConn(conn, mgr)
	}
}

func handleDeviceConn(conn net.Conn, mgr *Manager) {
	defer func() { _ = conn.Close() }()
	imei, err := readIMEI(conn)
	if err != nil {
		log.Printf("imei handshake: %v", err)
		return
	}
	if _, err := conn.Write([]byte{0x01}); err != nil {
		log.Printf("imei ack: %v", err)
		return
	}
	s := NewSession(conn, imei, mgr)
	mgr.Register(s)
	log.Printf("device online imei=%s remote=%s", imei, conn.RemoteAddr())
	s.Run()
	log.Printf("device offline imei=%s", imei)
}

func readIMEI(conn net.Conn) (string, error) {
	var lenBuf [2]byte
	if _, err := io.ReadFull(conn, lenBuf[:]); err != nil {
		return "", err
	}
	n := int(binary.BigEndian.Uint16(lenBuf[:]))
	if n <= 0 || n > imeiMaxLen {
		return "", fmt.Errorf("invalid imei length %d", n)
	}
	buf := make([]byte, n)
	if _, err := io.ReadFull(conn, buf); err != nil {
		return "", err
	}
	return string(buf), nil
}
