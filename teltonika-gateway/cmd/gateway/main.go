package main

import (
	"context"
	"log"
	"os/signal"
	"syscall"

	"evodrive/teltonika-gateway/internal/gateway"
	"evodrive/teltonika-gateway/internal/httpserver"
)

func main() {
	httpAddr, tcpAddr, token, onlineTTL := httpserver.FromEnv()
	mgr := gateway.NewManager()

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	go func() {
		if err := gateway.ServeTCP(tcpAddr, mgr); err != nil {
			log.Fatalf("tcp: %v", err)
		}
	}()

	srv := &httpserver.Server{
		Mgr:       mgr,
		HTTPAddr:  httpAddr,
		Token:     token,
		OnlineTTL: onlineTTL,
	}
	if err := srv.Run(ctx); err != nil {
		log.Printf("http server: %v", err)
	}
}
