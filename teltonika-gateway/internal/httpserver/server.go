package httpserver

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"
	"strings"
	"time"

	"evodrive/teltonika-gateway/internal/gateway"
)

type Server struct {
	Mgr       *gateway.Manager
	HTTPAddr  string
	Token     string
	OnlineTTL time.Duration
}

type commandReq struct {
	IMEI           string `json:"imei"`
	Command        string `json:"command"`
	TimeoutSeconds int    `json:"timeout_seconds"`
}

func gatewayLogCommandBody() bool {
	v := strings.TrimSpace(strings.ToLower(getenv("GATEWAY_LOG_COMMANDS", "1")))
	return v != "0" && v != "false" && v != "off"
}

func gatewayLogMaxCommandChars() int {
	const def = 512
	v := strings.TrimSpace(os.Getenv("GATEWAY_LOG_MAX_COMMAND_CHARS"))
	if v == "" {
		return def
	}
	n, err := strconv.Atoi(v)
	if err != nil || n <= 0 {
		return def
	}
	return n
}

func gatewayLogMaxResponseChars() int {
	const def = 1024
	v := strings.TrimSpace(os.Getenv("GATEWAY_LOG_MAX_RESPONSE_CHARS"))
	if v == "" {
		return def
	}
	n, err := strconv.Atoi(v)
	if err != nil || n <= 0 {
		return def
	}
	return n
}

func formatCommandAuditFragment(cmd string) string {
	if !gatewayLogCommandBody() {
		return fmt.Sprintf("cmd_bytes=%d redacted=1", len(cmd))
	}
	max := gatewayLogMaxCommandChars()
	show := cmd
	trunc := "truncated=0"
	if len(show) > max {
		show = show[:max]
		trunc = "truncated=1"
	}
	return fmt.Sprintf("cmd_bytes=%d cmd=%q %s", len(cmd), show, trunc)
}

func truncateLogString(s string, max int) string {
	if len(s) <= max {
		return s
	}
	return s[:max] + "…"
}

func (s *Server) auth(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if s.Token == "" {
			next(w, r)
			return
		}
		h := r.Header.Get("Authorization")
		const p = "Bearer "
		if !strings.HasPrefix(h, p) || strings.TrimSpace(h[len(p):]) != s.Token {
			log.Printf("gateway http unauthorized remote=%s path=%s", r.RemoteAddr, r.URL.Path)
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusUnauthorized)
			_, _ = w.Write([]byte(`{"ok":false,"error":"unauthorized"}`))
			return
		}
		next(w, r)
	}
}

func (s *Server) routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/commands", s.auth(s.commands))
	mux.HandleFunc("/devices/", s.auth(s.devices))
	return mux
}

func (s *Server) commands(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.NotFound(w, r)
		return
	}
	var body commandReq
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "invalid json"})
		return
	}
	body.IMEI = strings.TrimSpace(body.IMEI)
	body.Command = strings.TrimSpace(body.Command)
	if body.IMEI == "" || body.Command == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"ok": false, "error": "imei and command required"})
		return
	}
	timeout := time.Duration(body.TimeoutSeconds) * time.Second
	if timeout <= 0 {
		timeout = 30 * time.Second
	}
	if timeout > 120*time.Second {
		timeout = 120 * time.Second
	}

	audit := formatCommandAuditFragment(body.Command)
	log.Printf("codec12 http request remote=%s imei=%s timeout=%s %s", r.RemoteAddr, body.IMEI, timeout, audit)

	sess := s.Mgr.Get(body.IMEI)
	if sess == nil {
		log.Printf("codec12 http result imei=%s ok=false failure_code=device_offline %s", body.IMEI, audit)
		writeJSON(w, http.StatusNotFound, map[string]any{"ok": false, "failure_code": "device_offline", "error": "device not connected"})
		return
	}

	resp, err := sess.SendCodec12Command(body.Command, timeout)
	if err != nil {
		if errors.Is(err, gateway.ErrCommandTimeout) {
			log.Printf("codec12 http result imei=%s ok=false failure_code=timeout err=%v %s", body.IMEI, err, audit)
			writeJSON(w, http.StatusRequestTimeout, map[string]any{"ok": false, "failure_code": "timeout", "error": err.Error()})
			return
		}
		log.Printf("codec12 http result imei=%s ok=false failure_code=connection_lost err=%v %s", body.IMEI, err, audit)
		writeJSON(w, http.StatusBadGateway, map[string]any{"ok": false, "failure_code": "connection_lost", "error": err.Error()})
		return
	}
	maxResp := gatewayLogMaxResponseChars()
	respTrunc := "response_truncated=0"
	respLog := resp
	if len(respLog) > maxResp {
		respLog = truncateLogString(resp, maxResp)
		respTrunc = "response_truncated=1"
	}
	log.Printf("codec12 http result imei=%s ok=true response_bytes=%d %s response=%q %s", body.IMEI, len(resp), respTrunc, respLog, audit)
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "response": resp})
}

// Path: /devices/{imei}/status
func (s *Server) devices(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.NotFound(w, r)
		return
	}
	path := strings.Trim(r.URL.Path, "/")
	parts := strings.Split(path, "/")
	if len(parts) != 3 || parts[0] != "devices" || parts[2] != "status" {
		http.NotFound(w, r)
		return
	}
	imei := parts[1]
	online := s.Mgr.Online(imei, s.OnlineTTL)
	last := time.Time{}
	if sess := s.Mgr.Get(imei); sess != nil {
		last = sess.LastSeen()
	}
	out := map[string]any{
		"online": online,
		"status": map[bool]string{true: "online", false: "offline"}[online],
		"imei":   imei,
	}
	if !last.IsZero() {
		out["last_seen_at"] = last.UTC().Format(time.RFC3339Nano)
	}
	writeJSON(w, http.StatusOK, out)
}

func (s *Server) Run(ctx context.Context) error {
	srv := &http.Server{
		Addr:              s.HTTPAddr,
		Handler:           s.routes(),
		ReadHeaderTimeout: 10 * time.Second,
		ReadTimeout:       60 * time.Second,
		WriteTimeout:      125 * time.Second,
	}
	go func() {
		<-ctx.Done()
		shCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		_ = srv.Shutdown(shCtx)
	}()
	log.Printf("gateway http listening on %s", s.HTTPAddr)
	if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		return err
	}
	return nil
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

// FromEnv fills Token and addresses from environment (optional helpers for main).
func FromEnv() (httpAddr, tcpAddr, token string, onlineTTL time.Duration) {
	httpAddr = getenv("GATEWAY_HTTP_ADDR", ":8080")
	tcpAddr = getenv("GATEWAY_TCP_ADDR", ":5528")
	token = os.Getenv("GATEWAY_HTTP_TOKEN")
	onlineTTL = 90 * time.Second
	if v := os.Getenv("GATEWAY_ONLINE_TTL_SECONDS"); v != "" {
		if d, err := time.ParseDuration(v + "s"); err == nil {
			onlineTTL = d
		}
	}
	return
}

func getenv(k, def string) string {
	if v := os.Getenv(k); v != "" {
		return v
	}
	return def
}
