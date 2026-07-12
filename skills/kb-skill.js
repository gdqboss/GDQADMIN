import { pool } from '../db/connection.js';

// Simple embedding using character-level features (not real AI embedding)
export function getEmbedding(text) {
  if (!text) return [];
  const chars = text.toLowerCase().split('').filter(c => c.match(/[\u4e00-\u9fff\w]/));
  const freq = {};
  for (const c of chars) {
    freq[c] = (freq[c] || 0) + 1;
  }
  const keys = Object.keys(freq).sort();
  return keys.map(k => freq[k]);
}

export async function searchSimilarChunks(query, limit = 3) {
  try {
    const [rows] = await pool.query(
      "SELECT id, content, doc_title, 0.8 as score FROM ai_class_knowledge WHERE content LIKE ? LIMIT ?",
      [`%${query}%`, limit]
    );
    return rows.map(r => ({
      ...r,
      score: r.score || 0.8
    }));
  } catch (e) {
    console.error('[kb-skill] search error:', e.message);
    return [];
  }
}

export async function addLearningLog(pool, data) {
  try {
    // Check if learning_logs table exists, create if not
    await pool.query(
      `CREATE TABLE IF NOT EXISTS learning_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        session_id VARCHAR(255),
        user_message TEXT,
        ai_reply TEXT,
        source VARCHAR(100),
        intent VARCHAR(100),
        confidence DECIMAL(5,4) DEFAULT 0,
        kb_doc_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
    );
    await pool.query(
      'INSERT INTO learning_logs (user_id, session_id, user_message, ai_reply, source, intent, confidence, kb_doc_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
      [data.user_id, data.session_id, data.user_message, data.ai_reply, data.source, data.intent, data.confidence, data.kb_doc_id]
    );
  } catch (e) {
    console.error('[kb-skill] add learning log error:', e.message);
  }
}
