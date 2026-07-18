/**
 * minip-ai-marketing.js —— 营销 AI 投手
 * 2026-07-18 02:32 真写
 */
import { Router } from 'express';
import { pool } from '../db/connection.js';
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js';

const router = Router();
const READ = requirePermission(PERMISSIONS.AI_SUPERVISION_READ);
const WRITE = requirePermission(PERMISSIONS.AI_SUPERVISION_WRITE);

// AI 写营销文案（不调 LLM，直接基于规则）
router.post('/marketing/generate-copy', WRITE, async (req, res) => {
  try {
    const { activity_id, audience } = req.body;
    const [[act]] = await pool.query('SELECT title, description FROM minip_activities WHERE id = ?', [activity_id || 1]);
    if (!act) return res.json({ code: 404 });

    const audienceText = audience || '所有客户';
    const copy = `【${act.title}】\n\n亲爱的${audienceText}：\n\n${act.description || '限时优惠'}，机会难得，名额有限。立即点击参与，让您的项目更省钱！\n\n活动时间有限，先到先得 🌟`;

    res.json({ code: 0, data: { activity: act.title, audience: audienceText, generated_copy: copy, by: 'Marketing-AI-v2' } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// AI 预测 ROI（基于历史活动数据）
router.post('/marketing/predict-roi', WRITE, async (req, res) => {
  try {
    const { activity_id } = req.body;
    const [[act]] = await pool.query(
      'SELECT title, max_participants, current_participants, start_date, end_date FROM minip_activities WHERE id = ?',
      [activity_id || 1]
    );
    if (!act) return res.json({ code: 404 });

    const fillRate = act.max_participants > 0 ? act.current_participants / act.max_participants : 0;
    const days = Math.floor((new Date(act.end_date) - new Date(act.start_date)) / (1000*60*60*24));
    const predictedRoi = Math.round(fillRate * 100 + days * 0.5);

    res.json({
      code: 0,
      data: {
        activity: act.title,
        current_fill_rate: Math.round(fillRate * 100) + '%',
        predicted_roi_score: predictedRoi,
        recommendation: predictedRoi >= 80 ? '高 ROI，建议加大推广' : predictedRoi >= 50 ? '中等，需优化文案/目标' : '低 ROI，建议重新设计',
      }
    });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// 受众画像
router.post('/marketing/audience-profile', WRITE, async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT u.department, COUNT(DISTINCT u.id) as cnt
      FROM users u WHERE u.role IN ('member', 'customer') GROUP BY u.department ORDER BY cnt DESC
    `);
    res.json({ code: 0, data: { by_department: rows, total_segments: rows.length } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

export default router;
