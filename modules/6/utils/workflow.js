/**
 * Workflow Engine Utility
 * Handles workflow execution, task assignment, and approval routing
 */

import { pool } from '../db/connection.js'

/**
 * Parse workflow definition and generate instance code
 */
export function generateInstanceCode(workflowCode) {
  const timestamp = Date.now().toString(36).toUpperCase()
  const random = Math.random().toString(36).substring(2, 6).toUpperCase()
  return `${workflowCode}-${timestamp}-${random}`
}

/**
 * Evaluate condition expression
 * @param {string} condition - Condition expression like "days > 3" or "amount >= 5000"
 * @param {object} formData - Form data to evaluate against
 */
export function evaluateCondition(condition, formData) {
  if (!condition) return true

  try {
    // Simple expression parser for safety
    const match = condition.match(/^(\w+)\s*(>|>=|<|<=|==|!=)\s*(.+)$/)
    if (!match) return true

    const [, field, operator, valueStr] = match
    const fieldValue = formData[field]
    const compareValue = isNaN(valueStr) ? valueStr.replace(/['"]/g, '') : parseFloat(valueStr)

    switch (operator) {
      case '>': return fieldValue > compareValue
      case '>=': return fieldValue >= compareValue
      case '<': return fieldValue < compareValue
      case '<=': return fieldValue <= compareValue
      case '==': return fieldValue == compareValue
      case '!=': return fieldValue != compareValue
      default: return true
    }
  } catch (err) {
    console.error('Condition evaluation error:', err)
    return true
  }
}

/**
 * Find next node based on current node and conditions
 */
export function findNextNode(flowConfig, currentNodeId, formData) {
  const edges = flowConfig.edges || []
  const nodes = flowConfig.nodes || []

  // Find all outgoing edges from current node
  const outgoingEdges = edges.filter(edge => edge.from === currentNodeId)

  // Evaluate conditions and find matching edge
  for (const edge of outgoingEdges) {
    if (evaluateCondition(edge.condition, formData)) {
      return nodes.find(node => node.id === edge.to)
    }
  }

  return null
}

/**
 * Resolve assignee based on rule
 * @param {string} rule - Assignee rule: "direct_manager", "role:hr", "user:123", "department_manager"
 * @param {number} applicantId - Applicant user ID
 */
export async function resolveAssignee(rule, applicantId) {
  if (!rule) return null

  try {
    // Direct manager
    if (rule === 'direct_manager') {
      const [[applicant]] = await pool.query(
        'SELECT department FROM users WHERE id = ?',
        [applicantId]
      )
      if (!applicant || !applicant.department) return null

      const [[manager]] = await pool.query(
        `SELECT u.id FROM users u
         JOIN departments d ON d.manager_id = u.id
         WHERE d.name = ? AND u.status = 'active' LIMIT 1`,
        [applicant.department]
      )
      return manager?.id || null
    }

    // Role-based: role:hr, role:finance, role:admin
    if (rule.startsWith('role:')) {
      const role = rule.split(':')[1]
      const [[user]] = await pool.query(
        'SELECT id FROM users WHERE role = ? AND status = ? LIMIT 1',
        [role, 'active']
      )
      return user?.id || null
    }

    // Specific user: user:123
    if (rule.startsWith('user:')) {
      const userId = parseInt(rule.split(':')[1])
      return userId || null
    }

    // Department manager
    if (rule === 'department_manager') {
      const [[applicant]] = await pool.query(
        'SELECT department FROM users WHERE id = ?',
        [applicantId]
      )
      if (!applicant || !applicant.department) return null

      const [[manager]] = await pool.query(
        `SELECT u.id FROM users u
         JOIN departments d ON d.manager_id = u.id
         WHERE d.name = ? AND u.status = 'active' LIMIT 1`,
        [applicant.department]
      )
      return manager?.id || null
    }

    return null
  } catch (err) {
    console.error('Assignee resolution error:', err)
    return null
  }
}

/**
 * Start a new workflow instance
 */
export async function startWorkflow(workflowId, applicantId, title, formData, attachments = null) {
  const conn = await pool.getConnection()

  try {
    await conn.beginTransaction()

    // Get workflow definition
    const [[workflow]] = await conn.query(
      'SELECT * FROM workflow_definitions WHERE id = ? AND status = ?',
      [workflowId, 'active']
    )

    if (!workflow) {
      throw new Error('Workflow not found or inactive')
    }

    const flowConfig = JSON.parse(workflow.flow_config)
    const instanceCode = generateInstanceCode(workflow.code)

    // Create workflow instance
    const [result] = await conn.query(
      `INSERT INTO workflow_instances
       (workflow_id, instance_code, title, applicant_id, form_data, current_node, status, attachments)
       VALUES (?,?,?,?,?,?,?,?)`,
      [workflowId, instanceCode, title, applicantId, JSON.stringify(formData), 'start', 'pending',
       attachments ? JSON.stringify(attachments) : null]
    )

    const instanceId = result.insertId

    // Find first approval node
    const startNode = flowConfig.nodes.find(n => n.type === 'start')
    const firstNode = findNextNode(flowConfig, startNode.id, formData)

    if (firstNode && firstNode.type === 'approve') {
      // Resolve assignee
      const assigneeId = await resolveAssignee(firstNode.assignee_rule, applicantId)

      if (!assigneeId) {
        throw new Error(`Cannot resolve assignee for rule: ${firstNode.assignee_rule}`)
      }

      // Create first task
      await conn.query(
        `INSERT INTO workflow_tasks
         (instance_id, node_id, node_name, task_type, assignee_id, status)
         VALUES (?,?,?,?,?,?)`,
        [instanceId, firstNode.id, firstNode.name, 'approve', assigneeId, 'pending']
      )

      // Update instance current node
      await conn.query(
        'UPDATE workflow_instances SET current_node = ? WHERE id = ?',
        [firstNode.id, instanceId]
      )

      // Create notification for assignee
      await conn.query(
        `INSERT INTO notifications
         (user_id, type, title, content, link, related_id, related_type, priority)
         VALUES (?,?,?,?,?,?,?,?)`,
        [assigneeId, 'approval', `新的审批任务：${title}`,
         `您有一个待审批的${workflow.name}任务`,
         `/oa/workflow/detail/${instanceId}`, instanceId, 'workflow', 'normal']
      )
    }

    await conn.commit()
    return { instanceId, instanceCode }
  } catch (err) {
    await conn.rollback()
    throw err
  } finally {
    conn.release()
  }
}

/**
 * Process workflow task (approve/reject)
 */
export async function processTask(taskId, userId, action, comment = null) {
  const conn = await pool.getConnection()

  try {
    await conn.beginTransaction()

    // Get task
    const [[task]] = await conn.query(
      'SELECT * FROM workflow_tasks WHERE id = ? AND assignee_id = ? AND status = ?',
      [taskId, userId, 'pending']
    )

    if (!task) {
      throw new Error('Task not found or already processed')
    }

    // Get instance
    const [[instance]] = await conn.query(
      'SELECT * FROM workflow_instances WHERE id = ?',
      [task.instance_id]
    )

    if (!instance || instance.status !== 'pending') {
      throw new Error('Workflow instance not found or already completed')
    }

    // Get workflow definition
    const [[workflow]] = await conn.query(
      'SELECT * FROM workflow_definitions WHERE id = ?',
      [instance.workflow_id]
    )

    const flowConfig = JSON.parse(workflow.flow_config)
    const formData = JSON.parse(instance.form_data)

    // Update task status
    const taskStatus = action === 'approve' ? 'approved' : 'rejected'
    await conn.query(
      'UPDATE workflow_tasks SET status = ?, action = ?, comment = ?, completed_at = NOW() WHERE id = ?',
      [taskStatus, action, comment, taskId]
    )

    if (action === 'reject') {
      // Reject entire workflow
      await conn.query(
        'UPDATE workflow_instances SET status = ?, completed_at = NOW() WHERE id = ?',
        ['rejected', instance.id]
      )

      // Notify applicant
      await conn.query(
        `INSERT INTO notifications
         (user_id, type, title, content, link, related_id, related_type, priority)
         VALUES (?,?,?,?,?,?,?,?)`,
        [instance.applicant_id, 'approval', `审批被拒绝：${instance.title}`,
         `您的${workflow.name}申请已被拒绝`,
         `/oa/workflow/detail/${instance.id}`, instance.id, 'workflow', 'high']
      )
    } else {
      // Find next node
      const nextNode = findNextNode(flowConfig, task.node_id, formData)

      if (!nextNode || nextNode.type === 'end') {
        // Workflow completed
        await conn.query(
          'UPDATE workflow_instances SET status = ?, current_node = ?, completed_at = NOW() WHERE id = ?',
          ['approved', 'end', instance.id]
        )

        // Notify applicant
        await conn.query(
          `INSERT INTO notifications
           (user_id, type, title, content, link, related_id, related_type, priority)
           VALUES (?,?,?,?,?,?,?,?)`,
          [instance.applicant_id, 'approval', `审批通过：${instance.title}`,
           `您的${workflow.name}申请已通过`,
           `/oa/workflow/detail/${instance.id}`, instance.id, 'workflow', 'normal']
        )
      } else if (nextNode.type === 'approve') {
        // Create next task
        const assigneeId = await resolveAssignee(nextNode.assignee_rule, instance.applicant_id)

        if (!assigneeId) {
          throw new Error(`Cannot resolve assignee for rule: ${nextNode.assignee_rule}`)
        }

        await conn.query(
          `INSERT INTO workflow_tasks
           (instance_id, node_id, node_name, task_type, assignee_id, status)
           VALUES (?,?,?,?,?,?)`,
          [instance.id, nextNode.id, nextNode.name, 'approve', assigneeId, 'pending']
        )

        // Update instance current node
        await conn.query(
          'UPDATE workflow_instances SET current_node = ? WHERE id = ?',
          [nextNode.id, instance.id]
        )

        // Notify next assignee
        await conn.query(
          `INSERT INTO notifications
           (user_id, type, title, content, link, related_id, related_type, priority)
           VALUES (?,?,?,?,?,?,?,?)`,
          [assigneeId, 'approval', `新的审批任务：${instance.title}`,
           `您有一个待审批的${workflow.name}任务`,
           `/oa/workflow/detail/${instance.id}`, instance.id, 'workflow', 'normal']
        )
      }
    }

    await conn.commit()
    return { success: true }
  } catch (err) {
    await conn.rollback()
    throw err
  } finally {
    conn.release()
  }
}

/**
 * Transfer task to another user
 */
export async function transferTask(taskId, userId, targetUserId, reason) {
  const conn = await pool.getConnection()

  try {
    await conn.beginTransaction()

    const [[task]] = await conn.query(
      'SELECT * FROM workflow_tasks WHERE id = ? AND assignee_id = ? AND status = ?',
      [taskId, userId, 'pending']
    )

    if (!task) {
      throw new Error('Task not found or cannot be transferred')
    }

    // Update task assignee
    await conn.query(
      'UPDATE workflow_tasks SET assignee_id = ?, comment = ? WHERE id = ?',
      [targetUserId, `转交：${reason}`, taskId]
    )

    // Notify new assignee
    const [[instance]] = await conn.query(
      'SELECT title FROM workflow_instances WHERE id = ?',
      [task.instance_id]
    )

    await conn.query(
      `INSERT INTO notifications
       (user_id, type, title, content, link, related_id, related_type, priority)
       VALUES (?,?,?,?,?,?,?,?)`,
      [targetUserId, 'approval', `审批任务转交：${instance.title}`,
       `您收到一个转交的审批任务`,
       `/oa/workflow/detail/${task.instance_id}`, task.instance_id, 'workflow', 'normal']
    )

    await conn.commit()
    return { success: true }
  } catch (err) {
    await conn.rollback()
    throw err
  } finally {
    conn.release()
  }
}

/**
 * Withdraw workflow instance
 */
export async function withdrawWorkflow(instanceId, applicantId) {
  const [[instance]] = await pool.query(
    'SELECT * FROM workflow_instances WHERE id = ? AND applicant_id = ? AND status = ?',
    [instanceId, applicantId, 'pending']
  )

  if (!instance) {
    throw new Error('Workflow not found or cannot be withdrawn')
  }

  await pool.query(
    'UPDATE workflow_instances SET status = ?, completed_at = NOW() WHERE id = ?',
    ['withdrawn', instanceId]
  )

  // Cancel all pending tasks
  await pool.query(
    'UPDATE workflow_tasks SET status = ? WHERE instance_id = ? AND status = ?',
    ['cancelled', instanceId, 'pending']
  )

  return { success: true }
}

/**
 * Generate attendance monthly report
 */
export async function generateMonthlyReport(userId, year, month) {
  const conn = await pool.getConnection()

  try {
    const startDate = `${year}-${String(month).padStart(2, '0')}-01`
    const endDate = new Date(year, month, 0).toISOString().slice(0, 10)

    // Get attendance records
    const [records] = await conn.query(
      `SELECT * FROM attendance
       WHERE user_id = ? AND date >= ? AND date <= ?
       ORDER BY date`,
      [userId, startDate, endDate]
    )

    // Calculate statistics
    const stats = {
      work_days: records.length,
      actual_days: records.filter(r => r.clock_in && r.clock_out).length,
      late_count: records.filter(r => r.status === 'late').length,
      early_count: records.filter(r => r.status === 'early').length,
      absent_count: records.filter(r => !r.clock_in).length
    }

    // Get overtime hours
    const [[overtime]] = await conn.query(
      `SELECT COALESCE(SUM(hours), 0) as total_hours
       FROM overtime_records
       WHERE user_id = ? AND overtime_date >= ? AND overtime_date <= ? AND status = 'approved'`,
      [userId, startDate, endDate]
    )
    stats.overtime_hours = overtime.total_hours

    // Get leave days
    const [[leave]] = await conn.query(
      `SELECT COALESCE(SUM(JSON_EXTRACT(form_data, '$.days')), 0) as total_days
       FROM workflow_instances
       WHERE applicant_id = ? AND status = 'approved'
       AND JSON_EXTRACT(form_data, '$.start_date') >= ?
       AND JSON_EXTRACT(form_data, '$.start_date') <= ?`,
      [userId, startDate, endDate]
    )
    stats.leave_days = leave.total_days || 0

    // Insert or update report
    await conn.query(
      `INSERT INTO attendance_monthly_reports
       (user_id, year, month, work_days, actual_days, late_count, early_count, absent_count, overtime_hours, leave_days, report_data)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
       work_days = VALUES(work_days),
       actual_days = VALUES(actual_days),
       late_count = VALUES(late_count),
       early_count = VALUES(early_count),
       absent_count = VALUES(absent_count),
       overtime_hours = VALUES(overtime_hours),
       leave_days = VALUES(leave_days),
       report_data = VALUES(report_data),
       generated_at = NOW()`,
      [userId, year, month, stats.work_days, stats.actual_days, stats.late_count,
       stats.early_count, stats.absent_count, stats.overtime_hours, stats.leave_days,
       JSON.stringify({ records, stats })]
    )

    return stats
  } finally {
    conn.release()
  }
}
