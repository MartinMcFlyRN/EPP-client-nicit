-- Autenticazione a due fattori (TOTP) obbligatoria per gli utenti
ALTER TABLE users
  ADD COLUMN totp_secret TEXT NULL,
  ADD COLUMN totp_enabled_at DATETIME NULL,
  ADD COLUMN totp_last_step BIGINT NOT NULL DEFAULT 0,
  ADD COLUMN recovery_codes TEXT NULL;
