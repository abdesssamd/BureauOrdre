CREATE DATABASE IF NOT EXISTS gestion_fichiers
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;


SET NAMES 'utf8';

--
-- Set default database
--
USE gestion_fichiers;

--
-- Drop table `mail_object_templates`
--
DROP TABLE IF EXISTS mail_object_templates;

--
-- Drop table `password_reset_tokens`
--
DROP TABLE IF EXISTS password_reset_tokens;

--
-- Drop table `file_tag_links`
--
DROP TABLE IF EXISTS file_tag_links;

--
-- Drop table `file_tags`
--
DROP TABLE IF EXISTS file_tags;

--
-- Drop table `msg_messages`
--
DROP TABLE IF EXISTS msg_messages;

--
-- Drop table `msg_typing_status`
--
DROP TABLE IF EXISTS msg_typing_status;

--
-- Drop table `msg_presence_status`
--
DROP TABLE IF EXISTS msg_presence_status;

--
-- Drop table `msg_participants`
--
DROP TABLE IF EXISTS msg_participants;

--
-- Drop table `msg_conversations`
--
DROP TABLE IF EXISTS msg_conversations;

--
-- Drop table `mail_assignments`
--
DROP TABLE IF EXISTS mail_assignments;

--
-- Drop table `mail_comments`
--
DROP TABLE IF EXISTS mail_comments;

--
-- Drop table `mails`
--
DROP TABLE IF EXISTS mails;

--
-- Drop table `contacts`
--
DROP TABLE IF EXISTS contacts;

--
-- Drop table `fiscal_years`
--
DROP TABLE IF EXISTS fiscal_years;

--
-- Drop table `folder_shares`
--
DROP TABLE IF EXISTS folder_shares;

--
-- Drop table `share_group_members`
--
DROP TABLE IF EXISTS share_group_members;

--
-- Drop table `share_groups`
--
DROP TABLE IF EXISTS share_groups;

--
-- Drop table `file_shares`
--
DROP TABLE IF EXISTS file_shares;

--
-- Drop table `notifications`
--
DROP TABLE IF EXISTS notifications;

--
-- Drop table `files`
--
DROP TABLE IF EXISTS files;

--
-- Drop table `folders`
--
DROP TABLE IF EXISTS folders;

--
-- Drop table `logs`
--
DROP TABLE IF EXISTS logs;

--
-- Drop table `users`
--
DROP TABLE IF EXISTS users;

--
-- Drop table `departments`
--
DROP TABLE IF EXISTS departments;

--
-- Set default database
--
USE gestion_fichiers;

--
-- Create table `departments`
--
CREATE TABLE departments (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 4,
AVG_ROW_LENGTH = 5461,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create table `users`
--
CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  full_name varchar(150) NOT NULL,
  email varchar(150) NOT NULL,
  password_hash varchar(255) NOT NULL,
  role enum ('admin', 'directeur', 'secretaire', 'employe') DEFAULT 'employe',
  department_id int(11) DEFAULT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 9,
AVG_ROW_LENGTH = 3276,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `email` on table `users`
--
ALTER TABLE users
ADD UNIQUE INDEX email (email);

--
-- Create foreign key
--
ALTER TABLE users
ADD CONSTRAINT users_ibfk_1 FOREIGN KEY (department_id)
REFERENCES departments (id);

--
-- Create table `logs`
--
CREATE TABLE logs (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) DEFAULT NULL,
  action varchar(50) NOT NULL,
  details text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 5,
AVG_ROW_LENGTH = 4096,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE logs
ADD CONSTRAINT logs_ibfk_1 FOREIGN KEY (user_id)
REFERENCES users (id);

--
-- Create table `folders`
--
CREATE TABLE folders (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(200) NOT NULL,
  owner_id int(11) NOT NULL,
  department_id int(11) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE folders
ADD CONSTRAINT folders_ibfk_1 FOREIGN KEY (owner_id)
REFERENCES users (id);

--
-- Create foreign key
--
ALTER TABLE folders
ADD CONSTRAINT folders_ibfk_2 FOREIGN KEY (department_id)
REFERENCES departments (id);

--
-- Create table `files`
--
CREATE TABLE files (
  id int(11) NOT NULL AUTO_INCREMENT,
  owner_id int(11) NOT NULL,
  department_id int(11) DEFAULT NULL,
  folder_id int(11) DEFAULT NULL,
  original_name varchar(255) NOT NULL,
  stored_name varchar(255) NOT NULL,
  mime_type varchar(100) NOT NULL,
  size bigint(20) NOT NULL,
  uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  file_size int(11) NOT NULL DEFAULT 0,
  visibility enum ('public', 'private', 'department') DEFAULT 'department',
  ocr_content longtext DEFAULT NULL,
  ocr_status enum ('pending', 'processing', 'done') DEFAULT 'pending',
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 402,
AVG_ROW_LENGTH = 4701,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE files
ADD CONSTRAINT files_ibfk_1 FOREIGN KEY (owner_id)
REFERENCES users (id);

--
-- Create foreign key
--
ALTER TABLE files
ADD CONSTRAINT files_ibfk_2 FOREIGN KEY (department_id)
REFERENCES departments (id);

--
-- Create foreign key
--
ALTER TABLE files
ADD CONSTRAINT files_ibfk_3 FOREIGN KEY (folder_id)
REFERENCES folders (id);

--
-- Create table `notifications`
--
CREATE TABLE notifications (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  type enum ('share', 'upload', 'system') DEFAULT 'system',
  title varchar(255) NOT NULL,
  message text DEFAULT NULL,
  related_file_id int(11) DEFAULT NULL,
  is_read tinyint(1) DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 1528,
AVG_ROW_LENGTH = 124,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `idx_user_read` on table `notifications`
--
ALTER TABLE notifications
ADD INDEX idx_user_read (user_id, is_read);

--
-- Create index `idx_created` on table `notifications`
--
ALTER TABLE notifications
ADD INDEX idx_created (created_at);

--
-- Create index `idx_user_unread` on table `notifications`
--
ALTER TABLE notifications
ADD INDEX idx_user_unread (user_id, is_read, created_at);

--
-- Create foreign key
--
ALTER TABLE notifications
ADD CONSTRAINT notifications_ibfk_1 FOREIGN KEY (user_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE notifications
ADD CONSTRAINT notifications_ibfk_2 FOREIGN KEY (related_file_id)
REFERENCES files (id) ON DELETE CASCADE;

--
-- Create table `file_shares`
--
CREATE TABLE file_shares (
  id int(11) NOT NULL AUTO_INCREMENT,
  file_id int(11) NOT NULL,
  shared_with_user_id int(11) DEFAULT NULL,
  shared_with_department_id int(11) DEFAULT NULL,
  shared_with_group_id int(11) DEFAULT NULL,
  can_view tinyint(1) NOT NULL DEFAULT 1,
  can_download tinyint(1) NOT NULL DEFAULT 1,
  can_share tinyint(1) NOT NULL DEFAULT 0,
  can_edit tinyint(1) NOT NULL DEFAULT 0,
  expires_at date DEFAULT NULL,
  created_by int(11) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 5,
AVG_ROW_LENGTH = 4096,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE file_shares
ADD CONSTRAINT file_shares_ibfk_1 FOREIGN KEY (file_id)
REFERENCES files (id);

--
-- Create foreign key
--
ALTER TABLE file_shares
ADD CONSTRAINT file_shares_ibfk_2 FOREIGN KEY (shared_with_user_id)
REFERENCES users (id);

--
-- Create foreign key
--
ALTER TABLE file_shares
ADD CONSTRAINT file_shares_ibfk_3 FOREIGN KEY (shared_with_department_id)
REFERENCES departments (id);

--
-- Create table `share_groups`
--
CREATE TABLE share_groups (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(150) NOT NULL,
  description varchar(255) DEFAULT NULL,
  owner_id int(11) NOT NULL,
  department_id int(11) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create table `share_group_members`
--
CREATE TABLE share_group_members (
  group_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  role enum ('manager', 'member') DEFAULT 'member',
  added_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE share_group_members
ADD CONSTRAINT sgm_group_fk FOREIGN KEY (group_id)
REFERENCES share_groups (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE share_group_members
ADD CONSTRAINT sgm_user_fk FOREIGN KEY (user_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create table `folder_shares`
--
CREATE TABLE folder_shares (
  id int(11) NOT NULL AUTO_INCREMENT,
  folder_id int(11) NOT NULL,
  shared_with_user_id int(11) DEFAULT NULL,
  shared_with_department_id int(11) DEFAULT NULL,
  shared_with_group_id int(11) DEFAULT NULL,
  can_view tinyint(1) NOT NULL DEFAULT 1,
  can_download tinyint(1) NOT NULL DEFAULT 1,
  can_share tinyint(1) NOT NULL DEFAULT 0,
  can_edit tinyint(1) NOT NULL DEFAULT 0,
  expires_at date DEFAULT NULL,
  created_by int(11) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE folder_shares
ADD CONSTRAINT folder_shares_folder_fk FOREIGN KEY (folder_id)
REFERENCES folders (id) ON DELETE CASCADE;

--
-- Create table `fiscal_years`
--
CREATE TABLE fiscal_years (
  id int(11) NOT NULL AUTO_INCREMENT,
  year int(11) NOT NULL,
  is_active tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 2,
AVG_ROW_LENGTH = 16384,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `year` on table `fiscal_years`
--
ALTER TABLE fiscal_years
ADD UNIQUE INDEX year (year);

--
-- Create table `contacts`
--
CREATE TABLE contacts (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(150) NOT NULL,
  type enum ('administration', 'entreprise', 'citoyen') DEFAULT 'administration',
  address text DEFAULT NULL,
  email varchar(100) DEFAULT NULL,
  phone varchar(50) DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 247,
AVG_ROW_LENGTH = 74,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create table `mails`
--
CREATE TABLE mails (
  id int(11) NOT NULL AUTO_INCREMENT,
  file_id int(11) DEFAULT NULL,
  type enum ('arrivee', 'depart') NOT NULL,
  reference_no varchar(50) NOT NULL,
  external_ref varchar(100) DEFAULT NULL,
  correspondent varchar(150) NOT NULL,
  object varchar(255) NOT NULL,
  mail_date date NOT NULL,
  response_to_mail_id int(11) DEFAULT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  fiscal_year_id int(11) DEFAULT NULL,
  contact_id int(11) DEFAULT NULL,
  status enum ('nouveau', 'en_cours', 'traite', 'archive') DEFAULT 'nouveau',
  due_date date DEFAULT NULL,
  archive_box varchar(50) DEFAULT NULL COMMENT 'Numéro de la boite archive',
  archive_shelf varchar(50) DEFAULT NULL COMMENT 'Numéro du rayon/étagère',
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 467,
AVG_ROW_LENGTH = 217,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `reference_no` on table `mails`
--
ALTER TABLE mails
ADD UNIQUE INDEX reference_no (reference_no);

--
-- Create foreign key
--
ALTER TABLE mails
ADD CONSTRAINT mails_ibfk_1 FOREIGN KEY (file_id)
REFERENCES files (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE mails
ADD CONSTRAINT mails_ibfk_2 FOREIGN KEY (response_to_mail_id)
REFERENCES mails (id) ON DELETE SET NULL;

--
-- Create foreign key
--
ALTER TABLE mails
ADD CONSTRAINT mails_ibfk_3 FOREIGN KEY (fiscal_year_id)
REFERENCES fiscal_years (id);

--
-- Create foreign key
--
ALTER TABLE mails
ADD CONSTRAINT mails_ibfk_4 FOREIGN KEY (contact_id)
REFERENCES contacts (id) ON DELETE SET NULL;

--
-- Create table `mail_comments`
--
CREATE TABLE mail_comments (
  id int(11) NOT NULL AUTO_INCREMENT,
  mail_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  comment text NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  attachment_name varchar(255) DEFAULT NULL,
  attachment_stored varchar(255) DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE mail_comments
ADD CONSTRAINT comments_mail_fk FOREIGN KEY (mail_id)
REFERENCES mails (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE mail_comments
ADD CONSTRAINT comments_user_fk FOREIGN KEY (user_id)
REFERENCES users (id);

--
-- Create table `mail_assignments`
--
CREATE TABLE mail_assignments (
  id int(11) NOT NULL AUTO_INCREMENT,
  mail_id int(11) NOT NULL,
  assigned_by int(11) NOT NULL,
  assigned_to int(11) DEFAULT NULL,
  assigned_to_dept int(11) DEFAULT NULL,
  instruction varchar(255) DEFAULT NULL,
  deadline date DEFAULT NULL,
  status enum ('en_cours', 'traite', 'rejet') DEFAULT 'en_cours',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  processed_at datetime DEFAULT NULL,
  response_note text DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE mail_assignments
ADD CONSTRAINT assign_dept_fk FOREIGN KEY (assigned_to_dept)
REFERENCES departments (id);

--
-- Create foreign key
--
ALTER TABLE mail_assignments
ADD CONSTRAINT assign_mail_fk FOREIGN KEY (mail_id)
REFERENCES mails (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE mail_assignments
ADD CONSTRAINT assign_user_fk1 FOREIGN KEY (assigned_by)
REFERENCES users (id);

--
-- Create foreign key
--
ALTER TABLE mail_assignments
ADD CONSTRAINT assign_user_fk2 FOREIGN KEY (assigned_to)
REFERENCES users (id);

--
-- Create table `msg_conversations`
--
CREATE TABLE msg_conversations (
  id int(11) NOT NULL AUTO_INCREMENT,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 5,
AVG_ROW_LENGTH = 5461,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create table `msg_participants`
--
CREATE TABLE msg_participants (
  conversation_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  last_read_at datetime DEFAULT NULL,
  joined_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id)
)
ENGINE = INNODB,
AVG_ROW_LENGTH = 2048,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE msg_participants
ADD CONSTRAINT msg_participants_ibfk_1 FOREIGN KEY (conversation_id)
REFERENCES msg_conversations (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE msg_participants
ADD CONSTRAINT msg_participants_ibfk_2 FOREIGN KEY (user_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create table `msg_messages`
--
CREATE TABLE msg_messages (
  id int(11) NOT NULL AUTO_INCREMENT,
  conversation_id int(11) NOT NULL,
  sender_id int(11) NOT NULL,
  body text NOT NULL,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  attachment_stored varchar(255) DEFAULT NULL,
  attachment_original varchar(255) DEFAULT NULL,
  attachment_mime varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 10,
AVG_ROW_LENGTH = 1820,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `conv_created` on table `msg_messages`
--
ALTER TABLE msg_messages
ADD INDEX conv_created (conversation_id, created_at);

--
-- Create foreign key
--
ALTER TABLE msg_messages
ADD CONSTRAINT msg_messages_ibfk_1 FOREIGN KEY (conversation_id)
REFERENCES msg_conversations (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE msg_messages
ADD CONSTRAINT msg_messages_ibfk_2 FOREIGN KEY (sender_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create table `msg_typing_status`
--
CREATE TABLE msg_typing_status (
  conversation_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id)
)
ENGINE = INNODB,
AVG_ROW_LENGTH = 1024,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `idx_typing_conv_updated` on table `msg_typing_status`
--
ALTER TABLE msg_typing_status
ADD INDEX idx_typing_conv_updated (conversation_id, updated_at);

--
-- Create foreign key
--
ALTER TABLE msg_typing_status
ADD CONSTRAINT msg_typing_ibfk_1 FOREIGN KEY (conversation_id)
REFERENCES msg_conversations (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE msg_typing_status
ADD CONSTRAINT msg_typing_ibfk_2 FOREIGN KEY (user_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create table `msg_presence_status`
--
CREATE TABLE msg_presence_status (
  user_id int(11) NOT NULL,
  last_seen_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id)
)
ENGINE = INNODB,
AVG_ROW_LENGTH = 1024,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `idx_presence_last_seen` on table `msg_presence_status`
--
ALTER TABLE msg_presence_status
ADD INDEX idx_presence_last_seen (last_seen_at);

--
-- Create foreign key
--
ALTER TABLE msg_presence_status
ADD CONSTRAINT msg_presence_ibfk_1 FOREIGN KEY (user_id)
REFERENCES users (id) ON DELETE CASCADE;

--
-- Create table `file_tags`
--
CREATE TABLE file_tags (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(50) NOT NULL,
  color varchar(7) DEFAULT '#64748b',
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 6,
AVG_ROW_LENGTH = 3276,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `name` on table `file_tags`
--
ALTER TABLE file_tags
ADD UNIQUE INDEX name (name);

--
-- Create table `file_tag_links`
--
CREATE TABLE file_tag_links (
  file_id int(11) NOT NULL,
  tag_id int(11) NOT NULL,
  PRIMARY KEY (file_id, tag_id)
)
ENGINE = INNODB,
AVG_ROW_LENGTH = 8192,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create foreign key
--
ALTER TABLE file_tag_links
ADD CONSTRAINT file_tag_links_ibfk_1 FOREIGN KEY (file_id)
REFERENCES files (id) ON DELETE CASCADE;

--
-- Create foreign key
--
ALTER TABLE file_tag_links
ADD CONSTRAINT file_tag_links_ibfk_2 FOREIGN KEY (tag_id)
REFERENCES file_tags (id) ON DELETE CASCADE;

--
-- Create table `password_reset_tokens`
--
CREATE TABLE password_reset_tokens (
  id int(11) NOT NULL AUTO_INCREMENT,
  user_id int(11) NOT NULL,
  token varchar(64) NOT NULL,
  expires_at datetime NOT NULL,
  used tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

--
-- Create index `user_id` on table `password_reset_tokens`
--
ALTER TABLE password_reset_tokens
ADD INDEX user_id (user_id);

--
-- Create index `token` on table `password_reset_tokens`
--
ALTER TABLE password_reset_tokens
ADD INDEX token (token);

--
-- Create table `mail_object_templates`
--
CREATE TABLE mail_object_templates (
  id int(11) NOT NULL AUTO_INCREMENT,
  label varchar(255) NOT NULL,
  type enum ('arrivee', 'depart', 'both') DEFAULT 'both',
  sort_order int(11) DEFAULT 0,
  created_at datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
)
ENGINE = INNODB,
AUTO_INCREMENT = 10,
AVG_ROW_LENGTH = 1820,
CHARACTER SET utf8mb4,
COLLATE utf8mb4_general_ci;

-- 
-- Restore previous SQL mode
-- 
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;

-- 
-- Enable foreign keys
-- 
/*!40014 SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS */;

INSERT INTO departments VALUES
(1, 'Direction Générale'),
(2, 'Ressources Humaines'),
(3, 'Informatique');

-- 
-- Dumping data for table users
--
INSERT INTO users VALUES
(2, 'SuperAdmin', 'Administrateur Système', 'admin@admin.com', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 'admin', 1, 1, '2025-11-30 13:34:42'),
(3, 'admin1@admin.com', 'ahmed', 'admin1@admin.com', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 'employe', 1, 1, '2025-11-30 13:50:39');
