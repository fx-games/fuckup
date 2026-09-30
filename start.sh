#!/bin/sh
# Serves the site at http://localhost:${PORT:-8000} (needs PHP 8: brew install php)
# Admin pages stay disabled unless INTEL_PASS / UPLOAD_PASS are set.
cd "$(dirname "$0")" && exec php -d display_errors=0 -d max_execution_time=10 -d log_errors=1 \
  -d session.cookie_httponly=1 -d session.cookie_samesite=Strict -d session.use_strict_mode=1 \
  -S "localhost:${PORT:-8000}" router.php
