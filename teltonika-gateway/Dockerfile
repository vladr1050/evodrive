# Teltonika FMC TCP + internal HTTP API for EvoDrive car control.
FROM golang:1.21-alpine AS build
WORKDIR /src
COPY go.mod ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 go build -ldflags="-s -w" -o /gateway ./cmd/gateway

FROM alpine:3.19
RUN apk add --no-cache ca-certificates
COPY --from=build /gateway /gateway
EXPOSE 5528 8080
USER nobody
ENTRYPOINT ["/gateway"]
