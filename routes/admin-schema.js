import { Router } from 'express';

const router = Router();

// Schema definitions for admin panel
router.get('/schemas', (req, res) => {
  res.json({ code: 0, data: { schemas: [] } });
});

export default router;
