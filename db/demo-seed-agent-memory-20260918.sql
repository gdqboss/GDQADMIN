-- demo-seed-agent-memory-20260918.sql
-- 江小鱼立 (v2): 按 DB 真 schema 写
-- 5 表 schema 修正:
--   - memory_wisdom:  source_user_id / situation+action+reasoning / applied_count+effectiveness_score
--   - memory_profiles: growth_areas → watch_out
--   - memory_insights: scope enum / scope_ref_id / key_patterns+recommendations+risks / period_start+period_end
--   - secure_documents: tags longtext 不是 json / authorized_user_ids longtext 不是 json
--   - innovation_logs: 没改, 一致

SET NAMES utf8mb4;

-- 删除旧 demo (idempotent)
DELETE FROM memory_wisdom WHERE source_user_id BETWEEN 100 AND 103 OR source_user_id = 9;
DELETE FROM memory_insights WHERE user_id BETWEEN 100 AND 103 OR user_id IS NULL;
DELETE FROM memory_profiles WHERE user_id BETWEEN 100 AND 103;
DELETE FROM memory_events WHERE user_id BETWEEN 100 AND 103 OR user_id = 9;
DELETE FROM innovation_logs WHERE called_by_user_id BETWEEN 100 AND 103 OR called_by_user_id = 9;
DELETE FROM secure_documents WHERE owner_user_id BETWEEN 100 AND 103 OR owner_user_id = 9;

-- ═══════════════════════════════════════════════════════════════════════════
-- 1. memory_profiles — 4 个假员工画像
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO memory_profiles
  (user_id, thinking_style, reliability_score, speed_score, creativity_score, collaboration_score,
   trait_summary, best_at, watch_out, total_events, wisdom_count, last_analyzed_at)
VALUES
  (100, 'relationship_first', 0.92, 0.75, 0.60, 0.95,
   '客户关系型: 先建信任, 后谈生意. 决策慎重, 信任老客户',
   '老客户维护 / 客户投诉处理 / 商务谈判',
   '需要学数字化工具, 适应线上',
   25, 4, NOW()),
  (101, 'data_driven', 0.88, 0.92, 0.70, 0.65,
   '数据驱动型: 用数据决策, 喜欢做分析报表, 谨慎不冲动',
   '库存优化 / 销售预测 / 流程改进',
   '需要学柔性沟通, 别只讲数据',
   18, 3, NOW()),
  (102, 'quick_executor', 0.75, 0.95, 0.80, 0.70,
   '快速执行型: 想到就做, 不拖延, 但容易忽略细节',
   '展会执行 / 紧急任务 / 现场应变',
   '需要学仔细检查, 避免返工',
   12, 1, NOW()),
  (103, 'mentor_type', 0.95, 0.65, 0.55, 0.90,
   '导师型: 喜欢带新人, 愿意分享, 慢但稳, 团队核心',
   '新人培训 / 团队协作 / 经验传承',
   '需要学数字化, 保持不掉队',
   15, 2, NOW());

-- ═══════════════════════════════════════════════════════════════════════════
-- 2. memory_events — 60 条事件
-- ═══════════════════════════════════════════════════════════════════════════

-- user 100 (25 条)
INSERT INTO memory_events (user_id, source, source_ref_id, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at) VALUES
  (100, 'task', 1101, 'task_completed', '{"title":"VIP客户回访-王总","priority":"high","review_note":"送茶叶+聊女儿升学, 关系更近一步"}', '["客户关系","VIP"]', 0.90, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (100, 'task', 1102, 'task_completed', '{"title":"处理客户投诉-李姐","priority":"high","review_note":"先听完2小时抱怨, 最后她买了5件, 还介绍了闺蜜"}', '["客户关系","化投诉为机会"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (100, 'task', 1103, 'task_completed', '{"title":"新客户开发-张总","priority":"medium","review_note":"通过老客户介绍, 见1次就签了"}', '["客户开发","转介绍"]', 0.75, 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (100, 'task', 1104, 'task_completed', '{"title":"客户活动策划","priority":"medium","review_note":"办了一场品鉴会, 老客户带新客户来12人"}', '["客户关系","活动营销"]', 0.80, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  (100, 'attendance', 1201, 'attendance_pattern', '{"pattern":"连续3周满勤","insight":"稳定核心员工"}', '["出勤稳定"]', 0.85, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (100, 'work_log', 1301, 'log_insight', '{"content":"客户为什么留不住: 第一次报价最低的客户, 第二次最不靠谱","category":"销售经验"}', '["销售智慧"]', 0.88, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (100, 'work_log', 1302, 'log_insight', '{"content":"VIP客户送礼攻略: 实用 < 投其所好 < 记住他家人","category":"客户关系"}', '["VIP经营"]', 0.92, 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
  (100, 'agent_chat', 1401, 'agent_conversation', '{"topic":"怎么判断客户是不是真VIP","key_insight":"看回访频率, 不看客单价"}', '["客户判断"]', 0.85, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (100, 'oa_flow', 1501, 'oa_decision', '{"title":"VIP客户折扣申请","decision":"批8折","reason":"客户10年, 客单价高, 流失成本大"}', '["VIP政策"]', 0.85, 1, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- user 101 (18 条)
INSERT INTO memory_events (user_id, source, source_ref_id, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at) VALUES
  (101, 'task', 2101, 'task_completed', '{"title":"月度库存盘点","priority":"high","review_note":"发现3个产品账面库存错, 原因: 出库扫码漏扫"}', '["库存管理","数据准确性"]', 0.90, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (101, 'task', 2102, 'task_completed', '{"title":"销售预测表","priority":"medium","review_note":"做了Excel模型, 准确率从60%到82%"}', '["数据预测","建模"]', 0.85, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (101, 'task', 2103, 'task_completed', '{"title":"员工排班优化","priority":"medium","review_note":"数据看: 周五下午效率最低, 调整培训时段"}', '["排班","效率"]', 0.75, 0, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (101, 'work_log', 2301, 'log_insight', '{"content":"发现一个规律: 雨天客户退单率高3倍, 应该雨备物流方案","category":"数据洞察"}', '["数据洞察","运营"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (101, 'work_log', 2302, 'log_insight', '{"content":"新员工上手最快的方法: 让老员工陪跑3天, 不丢给文档","category":"管理经验"}', '["培训"]', 0.92, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  (101, 'agent_chat', 2401, 'agent_conversation', '{"topic":"怎么提升预测准确率","key_insight":"加外部因素: 天气/节假日/竞品促销"}', '["预测模型"]', 0.80, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (101, 'oa_flow', 2501, 'oa_decision', '{"title":"采购审批","decision":"按模型预测量采购, 不按经验","reason":"过去3个月预测准"}', '["数据决策"]', 0.85, 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- user 102 (12 条)
INSERT INTO memory_events (user_id, source, source_ref_id, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at) VALUES
  (102, 'task', 3101, 'task_completed', '{"title":"展会布展","priority":"high","review_note":"提前1天到场, 临时改了logo位置, 效果好"}', '["执行力","应变"]', 0.80, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (102, 'task', 3102, 'task_completed', '{"title":"紧急补货","priority":"high","review_note":"2小时调到货, 客户没发现断货"}', '["执行力","应急"]', 0.85, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (102, 'attendance', 3201, 'attendance_pattern', '{"pattern":"加班最多","insight":"敬业但需要监控防过劳"}', '["加班"]', 0.70, 0, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (102, 'work_log', 3301, 'log_insight', '{"content":"展会注意: 现场要带备用物料 (胶带/剪刀/充电宝), 上次差点栽"}', '["展会经验"]', 0.80, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (102, 'agent_chat', 3401, 'agent_conversation', '{"topic":"怎么快速搞定陌生客户","key_insight":"3分钟讲清楚3件事: 我是谁/你能得到啥/下一步"}', '["销售话术"]', 0.75, 0, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- user 103 (15 条)
INSERT INTO memory_events (user_id, source, source_ref_id, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at) VALUES
  (103, 'task', 4101, 'task_completed', '{"title":"新员工带教-小王","priority":"high","review_note":"3天陪跑, 第4天独立上岗, 客户反馈好"}', '["带教","新人"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (103, 'task', 4102, 'task_completed', '{"title":"团队周会组织","priority":"medium","review_note":"改成案例分享, 大家愿意说话了"}', '["管理","会议"]', 0.85, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (103, 'task', 4103, 'task_completed', '{"title":"老员工沟通-老李","priority":"medium","review_note":"发现他有情绪, 单独聊, 解开了心结"}', '["团队管理"]', 0.90, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  (103, 'work_log', 4301, 'log_insight', '{"content":"带新人秘诀: 别一次全讲, 每天3个点, 7天30个点够了","category":"培训经验"}', '["带教"]', 0.92, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (103, 'work_log', 4302, 'log_insight', '{"content":"老员工为啥不服: 不是不服钱, 是不服新来的, 让他当师父就服了","category":"管理智慧"}', '["管理","老员工"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  (103, 'oa_flow', 4501, 'oa_decision', '{"title":"新员工转正","decision":"通过","reason":"虽然业绩中等, 但学习态度好, 团队反馈好"}', '["用人"]', 0.85, 0, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- 老板 id=9 (5 条)
INSERT INTO memory_events (user_id, source, source_ref_id, event_type, event_data, ai_tags, ai_score, is_wisdom, occurred_at) VALUES
  (9, 'task', 9001, 'task_completed', '{"title":"供应商谈判","priority":"high","review_note":"看供应商脸色, 摸清他底线, 不急不躁"}', '["谈判","老板思维"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (9, 'task', 9002, 'task_completed', '{"title":"新店选址","priority":"high","review_note":"三看: 看人流, 看对手, 看房东"}', '["选址","老板思维"]', 0.95, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (9, 'task', 9003, 'task_completed', '{"title":"新业务评估","priority":"high","review_note":"砍了, 现金牛还没做大不分散精力"}', '["战略","聚焦"]', 0.92, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (9, 'oa_flow', 9501, 'oa_decision', '{"title":"员工加薪","decision":"老员工先加","reason":"稳定军心"}', '["薪酬","老板思维"]', 0.90, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  (9, 'oa_flow', 9502, 'oa_decision', '{"title":"设备采购","decision":"先小批试","reason":"供应商新, 跑量有风险"}', '["采购","老板思维"]', 0.88, 1, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- ═══════════════════════════════════════════════════════════════════════════
-- 3. memory_wisdom — 8 条知识财富 (用真字段)
--    字段: source_user_id / situation / action / reasoning / applied_count / effectiveness_score
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO memory_wisdom
  (source_user_id, category, title, situation, action, reasoning, tags, is_inheritance, inheritance_priority, applied_count, effectiveness_score, created_at)
VALUES
  (100, 'sales', '化投诉为机会: 听完2小时抱怨, 客户反买5件',
   '李姐退货时骂了2小时',
   '没说一句辩解, 全程听完. 她最后道歉, 买了5件, 还介绍闺蜜',
   '客户骂你 = 还在乎你, 不骂的才是真走了',
   '["客户关系","投诉处理"]', 1, 9, 0, 0.92, NOW()),
  (100, 'sales', 'VIP客户送礼心法: 实用不如投其所好',
   '王总客单价 50万/年',
   '送茶叶, 记住他家女儿今年高考',
   '判断VIP不是看客单价, 是看回访频率',
   '["VIP","送礼"]', 1, 8, 0, 0.90, NOW()),
  (101, 'data', '雨天退单率高3倍, 应提前雨备物流',
   '3个月数据: 雨天退单率 = 晴天3倍',
   '雨季提前一天发货 + 加缓冲包装',
   '不是产品质量问题, 是物流延误+客户心情差',
   '["数据洞察","物流"]', 1, 9, 0, 0.88, NOW()),
  (101, 'data', '预测准确率从60%→82%: 加外部变量',
   '基础模型只看历史销量, 准确率60%',
   '加天气/节假日/竞品促销, 提到82%',
   '模型加外部变量 = 准确率大幅提升',
   '["预测模型"]', 0, NULL, 2, 0.85, NOW()),
  (102, 'execution', '展会现场必备清单: 胶带/剪刀/充电宝',
   '上次展会差点栽: 胶带没带, 临时借',
   '现在每次带3份备用物料',
   '现场问题永远比你想象的多',
   '["展会","应急"]', 0, NULL, 0, 0.82, NOW()),
  (103, 'management', '带新人心法: 每天3个点, 7天够了',
   '小王带教3天独立上岗',
   '每天只讲3个点, 不一次讲50个',
   '一次讲太多, 新人消化不了, 还觉得自己笨',
   '["带教","新人培训"]', 1, 10, 5, 0.95, NOW()),
  (103, 'management', '老员工为啥不服新来的: 让他当师父',
   '老李不服新主管',
   '让新主管拜老李为师, 教了1个月反过来帮',
   '老员工要的是尊重, 不是钱',
   '["管理","老员工"]', 1, 9, 3, 0.92, NOW()),
  (9, 'strategy', '新业务评估铁律: 现金牛没做大不分散',
   '有人提议做新业务线',
   '砍了',
   '手里有1个能赚钱的, 别去赌第2个. 现金牛 = 行业前3 + 有团队能接管, 再开',
   '["战略","老板思维","接班"]', 1, 10, 0, 0.95, NOW());

-- ═══════════════════════════════════════════════════════════════════════════
-- 4. memory_insights — 3 条 AI 报告 (按真字段)
--    字段: scope enum / scope_ref_id / period_start+period_end / key_patterns+recommendations+risks
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO memory_insights
  (user_id, scope, scope_ref_id, period, period_start, period_end, title, summary,
   key_patterns, recommendations, risks, ai_model, ai_tokens_used, generated_at)
VALUES
  (100, 'user', 100, 'weekly', DATE_SUB(CURDATE(), INTERVAL 7 DAY), CURDATE(),
   '本周周报: 客户关系型员工表现',
   '本周关系型销售表现优秀, VIP客户经营有独到见解',
   '["VIP送礼心法独到","化投诉为机会","关系网稳固"]',
   '["配一个数据助理, 让TA专注客户"]',
   '["数字化工具使用弱","报表不擅长"]',
   'minimax-anthropic', 850, NOW()),
  (101, 'user', 101, 'weekly', DATE_SUB(CURDATE(), INTERVAL 7 DAY), CURDATE(),
   '本周周报: 数据驱动型员工表现',
   '本周数据驱动决策表现突出, 发现雨天退单规律',
   '["数据建模能力突出","流程优化意识强"]',
   '["每月1次对外培训, 锻炼表达"]',
   '["柔性沟通弱","团队协作偏低"]',
   'minimax-anthropic', 920, NOW()),
  (NULL, 'company', NULL, 'monthly', DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(),
   '公司月度报告: AI 数据中心全景',
   '关系型(100) + 数据型(101) 是黄金组合 / 导师型(103) 是稳定器',
   '["关系+数据=黄金组合","导师是稳定器","执行型加班多"]',
   '["给100配数据助理","给102设加班上限","举办AI数据中心月度复盘"]',
   '["数据型与执行型协作偏低","导师型可能过劳"]',
   'minimax-anthropic', 1500, NOW());

-- ═══════════════════════════════════════════════════════════════════════════
-- 5. secure_documents — 3 个机密 (tags/authorized_user_ids 改 longtext 字符串)
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO secure_documents
  (owner_user_id, title, category, tags, encrypted_content, content_hash, encryption_iv, content_size,
   uploaded_by, authorized_user_ids, is_active)
VALUES
  (9, 'XX 板材硬度配方 v3 (机密)', 'formula',
   '["机密","配方","板材"]',
   'PLACEHOLDER_ENCRYPTED_001',
   SHA2('板材硬度配方 v3 内容: 树脂 60% + 固化剂 12% + 添加剂 A 5%', 256),
   'demo_iv_001_pad_32_chars_ok__',
   120,
   9,
   '[101,103]',
   1),
  (9, 'VIP客户-王总 关系档案', 'customer',
   '["机密","客户","VIP"]',
   'PLACEHOLDER_ENCRYPTED_002',
   SHA2('VIP王总: 客单价50万, 女儿今年高考, 喜欢普洱茶, 忌讳别提价格', 256),
   'demo_iv_002_pad_32_chars_ok__',
   80,
   9,
   '[100]',
   1),
  (9, '老板决策日志 - 砍新业务的思考', 'decision',
   '["机密","决策","战略"]',
   'PLACEHOLDER_ENCRYPTED_003',
   SHA2('砍新业务: 现金牛彩美特还没行业前3, 分散精力=两边都做不好', 256),
   'demo_iv_003_pad_32_chars_ok__',
   150,
   9,
   '[]',
   1);

-- ═══════════════════════════════════════════════════════════════════════════
-- 6. innovation_logs — 5 条老板问 AI 创新
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO innovation_logs
  (called_by_user_id, document_id, question, question_hash, constraint_rules,
   llm_provider, llm_model, response_text, response_tokens, response_latency_ms, called_at)
VALUES
  (9, 1, '如何增加板材的硬度?', SHA2('如何增加板材的硬度?', 256),
   '不准透露配方成分 / 仅基于公开知识 / 标参考文献',
   'minimax-anthropic', 'MiniMax-M3-8k',
   '公开知识建议:\n1. 增加固化剂比例 (从 10% 到 15%)\n2. 加交联剂 (如三聚氰胺)\n3. 高温高压固化 (120°C, 8MPa)\n参考文献: 《复合材料学》第3章 / 专利 CN1234567A',
   850, 6150, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
  (9, 1, '板材硬度测试标准有哪些?', SHA2('板材硬度测试标准有哪些?', 256),
   '不准透露配方成分',
   'minimax-anthropic', 'MiniMax-M3-8k',
   '常见标准:\n- GB/T 17657 (国标)\n- ASTM D1037 (美标)\n- JIS A5905 (日标)\n主要测试方法: 洛氏硬度 / 布氏硬度 / 维氏硬度',
   620, 5200, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
  (9, 2, 'VIP王总的女儿高考志愿怎么选?', SHA2('VIP王总的女儿高考志愿怎么选?', 256),
   '不准透露客户姓名 / 仅基于公开高考信息',
   'minimax-anthropic', 'MiniMax-M3-8k',
   '公开建议:\n- 看分数 vs 一分一段表\n- 看专业就业率\n- 看城市\n- 考虑家庭背景 (经济承受力)',
   480, 4100, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
  (9, 3, '什么时候可以重启新业务?', SHA2('什么时候可以重启新业务?', 256),
   '基于公开战略理论',
   'minimax-anthropic', 'MiniMax-M3-8k',
   '战略框架建议:\n- 安索夫矩阵: 现有客户×新产品\n- 钱德勒战略跟随: 主业稳固再扩张\n- 巴菲特: 能力圈原则',
   550, 4800, DATE_SUB(NOW(), INTERVAL 4 HOUR)),
  (9, NULL, '老板怎么培养接班人?', SHA2('老板怎么培养接班人?', 256),
   '基于公开管理学',
   'minimax-anthropic', 'MiniMax-M3-8k',
   '公开建议:\n- 早放手 (30% 决策权)\n- 给试错空间\n- 定期复盘\n- 让他见客户 / 见供应商\n- 读 EMBA / 找导师',
   720, 5800, DATE_SUB(NOW(), INTERVAL 5 HOUR));

-- 完了 (2026-09-18 江小鱼)