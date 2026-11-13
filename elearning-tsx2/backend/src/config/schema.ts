import {db} from './database';
export async function ensureSchema(){
 await db.query(`CREATE TABLE IF NOT EXISTS contact_messages (
 message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,email VARCHAR(254) NOT NULL,subject VARCHAR(200) NOT NULL,
 message TEXT NOT NULL,status ENUM('unread','read','resolved') NOT NULL DEFAULT 'unread',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX contact_status_date(status,created_at)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`);
 // Normalize links from the imported snapshot to the Next.js routes.
 await db.query("UPDATE notifications SET link=CONCAT('/courses/',SUBSTRING_INDEX(link,'=',-1)) WHERE link REGEXP '^/?courses[.]php[?]id=[0-9]+$'");
}
