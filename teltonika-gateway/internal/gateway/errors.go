package gateway

import "errors"

// ErrCommandTimeout is returned when the device does not send a Codec12 response in time.
var ErrCommandTimeout = errors.New("device response timeout")
