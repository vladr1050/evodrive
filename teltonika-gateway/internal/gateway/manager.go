package gateway

import (
	"sync"
	"time"
)

// Manager tracks live TCP sessions by IMEI (single session per IMEI).
type Manager struct {
	mu       sync.RWMutex
	sessions map[string]*Session // imei -> session
}

func NewManager() *Manager {
	return &Manager{sessions: make(map[string]*Session)}
}

func (m *Manager) Register(s *Session) {
	m.mu.Lock()
	defer m.mu.Unlock()
	if old, ok := m.sessions[s.IMEI]; ok && old != s {
		_ = old.Close()
	}
	m.sessions[s.IMEI] = s
}

func (m *Manager) Remove(s *Session) {
	m.mu.Lock()
	defer m.mu.Unlock()
	if cur, ok := m.sessions[s.IMEI]; ok && cur == s {
		delete(m.sessions, s.IMEI)
	}
}

func (m *Manager) Get(imei string) *Session {
	m.mu.RLock()
	defer m.mu.RUnlock()
	return m.sessions[imei]
}

// Online reports whether a session exists and was recently active.
func (m *Manager) Online(imei string, ttl time.Duration) bool {
	s := m.Get(imei)
	if s == nil {
		return false
	}
	return time.Since(s.LastSeen()) <= ttl
}
