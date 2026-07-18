/**
 * minip-ai-hr.js —— HR AI 招聘 & 面试助手
 * 2026-07-18 02:32 真写
 */
import { Router } from 'express';
import { pool } from '../db/connection.js';
import { requirePermission, PERMISSIONS } from '../middleware/rbac.js';

const router = Router();
const READ = requirePermission(PERMISSIONS.AI_SUPERVISION_READ);
const WRITE = requirePermission(PERMISSIONS.AI_SUPERVISION_WRITE);

// 简历评分（基于 minip_hr_recruit 的 requirement + 自动生成 skill match）
router.post('/hr/score-resume-deep', WRITE, async (req, res) => {
  try {
    const { job_id, candidate_skills, candidate_experience_years } = req.body;
    const [[job]] = await pool.query(
      'SELECT id, title, requirement FROM minip_hr_recruit WHERE id = ? LIMIT 1',
      [job_id || 1]
    );
    if (!job) return res.json({ code: 404, message: 'job not found' });

    const reqText = (job.requirement || '').toLowerCase();
    const candSkills = Array.isArray(candidate_skills) ? candidate_skills.map(s => s.toLowerCase()) : [];
    const matches = candSkills.filter(s => reqText.includes(s)).length;
    const skillScore = candSkills.length === 0 ? 50 : Math.round((matches / candSkills.length) * 60 + 40);

    const years = candidate_experience_years || 0;
    const expScore = years >= 3 ? 100 : Math.round((years / 3) * 80);

    const overall = Math.round(skillScore * 0.6 + expScore * 0.4);

    res.json({
      code: 0,
      data: {
        job: job.title,
        score: overall,
        skill_match: skillScore,
        experience_match: expScore,
        matched_skills: candSkills.filter(s => reqText.includes(s)),
        recommendation: overall >= 80 ? '强烈推荐面试' : overall >= 60 ? '可考虑面试' : '不推荐',
        ai_comment: `候选人 ${candSkills.join('/') || '未知技能'} 与 ${job.title} 匹配度 ${overall} 分。`,
      }
    });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// AI 模拟面试官生成 5 个问题
router.post('/hr/generate-interview', WRITE, async (req, res) => {
  try {
    const { job_id } = req.body;
    const [[job]] = await pool.query('SELECT title, requirement FROM minip_hr_recruit WHERE id = ? LIMIT 1', [job_id || 1]);
    if (!job) return res.json({ code: 404 });

    // 基于 requirement 关键词生成问题
    const keywords = (job.requirement || '').split(/[,，、 ]/).filter(k => k.length > 0).slice(0, 5);
    const questions = [
      `请介绍一下你在 ${job.title} 相关的最大项目经验`,
      `你如何理解 ${job.title} 的核心职责？`,
      ...keywords.map(k => `在 ${k} 方面，你有哪些具体方法论或案例？`),
      `反问环节：你有什么想了解我们团队的？`,
    ].slice(0, 5);

    res.json({ code: 0, data: { job: job.title, questions, generated_by: 'HR-AI-v2' } });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

// 候选人画像（聚合评分）
router.post('/hr/candidate-profile', WRITE, async (req, res) => {
  try {
    const { candidate_id } = req.body;
    const [[c]] = await pool.query('SELECT * FROM minip_hr_employees WHERE id = ?', [candidate_id || 1]);
    if (!c) return res.json({ code: 404 });

    res.json({
      code: 0,
      data: {
        candidate: c.name,
        department: c.department,
        position: c.position,
        tenure_months: c.hired_at ? Math.floor((Date.now() - new Date(c.hired_at)) / (1000*60*60*24*30)) : null,
        profile: {
          experience_level: c.position?.includes('高级') ? 'senior' : c.position?.includes('初级') ? 'junior' : 'mid',
          stability: c.status === 'active' ? '稳定' : '不稳定',
        },
      }
    });
  } catch (err) {
    res.json({ code: 500, message: err.message });
  }
});

export default router;
