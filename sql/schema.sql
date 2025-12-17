CREATE DATABASE IF NOT EXISTS Bip
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE Bip;

-- 1. User accounts (anonymous, with profile color)

CREATE TABLE User (
  userID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  passwordHash VARCHAR(255) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  profileColor CHAR(7) NOT NULL DEFAULT '#888888',
  isAdmin TINYINT(1) NOT NULL DEFAULT 0,
  accountStatus ENUM('ACTIVE','WARNED','BANNED') NOT NULL DEFAULT 'ACTIVE',
  hasSeenWelcome TINYINT(1) NOT NULL DEFAULT 0,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastLoginAt DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Online presence and mode

CREATE TABLE UserStatus (
  userID INT UNSIGNED NOT NULL,
  mode ENUM('IDLE','LISTENER_AVAILABLE','LOOKING_TO_TALK','IN_CONVERSATION')
       NOT NULL DEFAULT 'IDLE',
  isOnline TINYINT(1) NOT NULL DEFAULT 0,
  lastSeenAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (userID),
  CONSTRAINT fk_UserStatus_User
    FOREIGN KEY (userID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Conversations (one-to-one threads)

CREATE TABLE Conversation (
  conversationID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  createdByUserID INT UNSIGNED NOT NULL,
  initiatorRole ENUM('TALKER') NOT NULL DEFAULT 'TALKER',
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_Conversation_CreatedBy
    FOREIGN KEY (createdByUserID) REFERENCES User(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Participants in a conversation (talker + listener)

CREATE TABLE ConversationParticipant (
  conversationID INT UNSIGNED NOT NULL,
  userID INT UNSIGNED NOT NULL,
  roleInConversation ENUM('TALKER','LISTENER') NOT NULL,
  joinedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversationID, userID),
  CONSTRAINT fk_ConvPart_Conversation
    FOREIGN KEY (conversationID) REFERENCES Conversation(conversationID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ConvPart_User
    FOREIGN KEY (userID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Messages (chat and system)

CREATE TABLE Message (
  messageID BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversationID INT UNSIGNED NOT NULL,
  senderUserID INT UNSIGNED NULL,
  content TEXT NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  isSystem TINYINT(1) NOT NULL DEFAULT 0,
  isFlagged TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_Message_Conversation
    FOREIGN KEY (conversationID) REFERENCES Conversation(conversationID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_Message_Sender
    FOREIGN KEY (senderUserID) REFERENCES User(userID)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_Message_conversationID_createdAt
  ON Message (conversationID, createdAt);

-- 6. Flag Types (dynamic flags for messages and users)

CREATE TABLE FlagType (
  flagTypeID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  displayName VARCHAR(100) NOT NULL,
  description TEXT NULL,
  category ENUM('MESSAGE','USER','BOTH') NOT NULL,
  severity ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default flags
INSERT INTO FlagType (code, displayName, description, category, severity) VALUES
-- Message flags
('INSULTING', 'Insulting', 'Rude or insulting language', 'MESSAGE', 'MEDIUM'),
('SEXUAL', 'Sexual Content', 'Inappropriate sexual content', 'MESSAGE', 'HIGH'),
('THREATENING', 'Threatening', 'Threats or violent language', 'MESSAGE', 'CRITICAL'),
('DISCRIMINATORY', 'Discriminatory', 'Discriminatory or hateful speech', 'MESSAGE', 'CRITICAL'),
('SPAM', 'Spam', 'Spam or repetitive content', 'MESSAGE', 'LOW'),
('HARASSMENT', 'Harassment', 'Harassing behavior', 'MESSAGE', 'HIGH'),

-- User flags (positive)
('HELPFUL', 'Helpful', 'Helpful and supportive', 'USER', 'LOW'),
('SAFE', 'Safe', 'Makes you feel safe', 'USER', 'LOW'),
('EMPATHETIC', 'Empathetic', 'Shows empathy and understanding', 'USER', 'LOW'),

-- User flags (negative)
('TOXIC', 'Toxic', 'Toxic behavior', 'USER', 'HIGH'),
('DISRESPECTFUL', 'Disrespectful', 'Disrespectful or rude', 'USER', 'MEDIUM'),
('INAPPROPRIATE', 'Inappropriate', 'Inappropriate behavior', 'USER', 'MEDIUM'),

-- Both
('OTHER', 'Other', 'Other issue (please specify)', 'BOTH', 'LOW');

-- 7. Message-level reports (flags)

CREATE TABLE Report (
  reportID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  messageID BIGINT UNSIGNED NOT NULL,
  reporterUserID INT UNSIGNED NOT NULL,
  flagTypeID INT UNSIGNED NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason TEXT NULL,
  status ENUM('PENDING','CONFIRMED','DISCARDED') NOT NULL DEFAULT 'PENDING',
  handledByAdminID INT UNSIGNED NULL,
  handledAt DATETIME NULL,
  adminComment TEXT NULL,
  CONSTRAINT fk_Report_Message
    FOREIGN KEY (messageID) REFERENCES Message(messageID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_Report_Reporter
    FOREIGN KEY (reporterUserID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_Report_Admin
    FOREIGN KEY (handledByAdminID) REFERENCES User(userID)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_Report_FlagType
    FOREIGN KEY (flagTypeID) REFERENCES FlagType(flagTypeID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_Report_status_createdAt
  ON Report (status, createdAt);

-- 8. Admin penalties (warnings / bans)

CREATE TABLE UserPenalty (
  penaltyID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  targetUserID INT UNSIGNED NOT NULL,
  adminUserID INT UNSIGNED NOT NULL,
  penaltyType ENUM('WARNING','TEMP_BAN','PERMA_BAN') NOT NULL,
  reason TEXT NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expiresAt DATETIME NULL,
  CONSTRAINT fk_Penalty_TargetUser
    FOREIGN KEY (targetUserID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_Penalty_AdminUser
    FOREIGN KEY (adminUserID) REFERENCES User(userID)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. User-to-user ratings / safelist flags

CREATE TABLE UserRating (
  ratingID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  raterUserID INT UNSIGNED NOT NULL,
  targetUserID INT UNSIGNED NOT NULL,
  flagTypeID INT UNSIGNED NULL,
  ratingValue TINYINT UNSIGNED NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_UserRating_Rater
    FOREIGN KEY (raterUserID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_UserRating_Target
    FOREIGN KEY (targetUserID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_UserRating_FlagType
    FOREIGN KEY (flagTypeID) REFERENCES FlagType(flagTypeID)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_UserRating_targetUserID
  ON UserRating (targetUserID);

-- 10. Optional explicit safelist (can also be derived from UserRating)

CREATE TABLE SafeUser (
  userID INT UNSIGNED NOT NULL,
  safeUserID INT UNSIGNED NOT NULL,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (userID, safeUserID),
  CONSTRAINT fk_SafeUser_User
    FOREIGN KEY (userID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_SafeUser_SafeUser
    FOREIGN KEY (safeUserID) REFERENCES User(userID)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
