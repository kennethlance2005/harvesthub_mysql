CREATE TABLE SIGNUP_EMAIL_VERIFICATION (
    Email        VARCHAR(190)  NOT NULL PRIMARY KEY,
    FirstName    VARCHAR(60)   NOT NULL,
    LastName     VARCHAR(60)   NOT NULL,
    Age          INT           NOT NULL,
    Location     VARCHAR(60)   NOT NULL,
    PasswordHash VARCHAR(255)  NOT NULL,
    CodeHash     VARCHAR(255)  NOT NULL,
    ExpiresAt    DATETIME      NOT NULL,
    Attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    LastSentAt   DATETIME      NOT NULL,
    RequestedAt  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_signup_email_verification_expiry (ExpiresAt)
) ENGINE=InnoDB;

ALTER TABLE PASSWORD_RESET
    ADD COLUMN LastSentAt DATETIME NULL;
