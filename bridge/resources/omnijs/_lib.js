function ok(data) {
  return JSON.stringify({ ok: true, data: data });
}

function fail(code, message) {
  return JSON.stringify({ ok: false, error: { code: code, message: message } });
}

function iso(d) {
  return d ? d.toISOString() : null;
}

function taskStatusName(s) {
  const names = new Map([
    [Task.Status.Available, "available"],
    [Task.Status.Blocked, "blocked"],
    [Task.Status.Completed, "completed"],
    [Task.Status.Dropped, "dropped"],
    [Task.Status.DueSoon, "due_soon"],
    [Task.Status.Next, "next"],
    [Task.Status.Overdue, "overdue"],
  ]);
  return names.get(s) || String(s);
}

function projectStatusName(s) {
  const names = new Map([
    [Project.Status.Active, "active"],
    [Project.Status.Done, "done"],
    [Project.Status.Dropped, "dropped"],
    [Project.Status.OnHold, "on_hold"],
  ]);
  return names.get(s) || String(s);
}

function projectStatusFromName(name) {
  const statuses = new Map([
    ["active", Project.Status.Active],
    ["done", Project.Status.Done],
    ["dropped", Project.Status.Dropped],
    ["on_hold", Project.Status.OnHold],
  ]);
  return statuses.get(name);
}

function findOrCreateTag(name) {
  return flattenedTags.find(x => x.name === name) || new Tag(name);
}

function applyTaskFields(t, args, preBuiltRule) {
  if (args.name !== undefined && args.name !== null) t.name = args.name;
  if (args.note !== undefined && args.note !== null) t.note = args.note;
  if (args.due !== undefined) t.dueDate = args.due ? new Date(args.due) : null;
  if (args.defer !== undefined) t.deferDate = args.defer ? new Date(args.defer) : null;
  if (args.flagged !== undefined && args.flagged !== null) t.flagged = args.flagged;
  if (args.estimated_minutes !== undefined && args.estimated_minutes !== null) t.estimatedMinutes = args.estimated_minutes;
  if (args.tags !== undefined && args.tags !== null) {
    t.clearTags();
    for (const name of args.tags) t.addTag(findOrCreateTag(name));
  }
  // Recurrence is applied last, from a rule already validated/built by
  // buildRepetitionRule (called before any mutation — see H1). `preBuiltRule`
  // is undefined (no change), null (clear), or a Task.RepetitionRule.
  if (preBuiltRule !== undefined) {
    t.repetitionRule = preBuiltRule; // null clears it
  }
}

// Validate + construct a task's repetition rule WITHOUT touching the task.
// Returns: undefined (no recurrence change), null (clear), or a
// Task.RepetitionRule. Throws on invalid input, so callers run it before any
// mutation and keep create/update atomic with respect to a bad rule.
// `existing` is the task's current repetitionRule (for update method-preservation).
function buildRepetitionRule(args, existing) {
  if (args.repetition_rule === undefined && args.repetition_method === undefined) {
    return undefined;
  }
  if (args.repetition_rule === null) {
    return null;
  }
  // Method-only change: rebuild from the existing rule string.
  if (args.repetition_rule === undefined) {
    if (!existing) throw new Error("repetition_method given but the task has no repetition rule to modify");
    const m = repetitionMethodFromName(args.repetition_method);
    if (m === undefined) throw new Error("Unknown repetition_method: " + args.repetition_method);
    return new Task.RepetitionRule(existing.ruleString, m);
  }
  // Rule given. Preserve the existing method unless a new one is supplied.
  let method;
  if (args.repetition_method) {
    method = repetitionMethodFromName(args.repetition_method);
    if (method === undefined) throw new Error("Unknown repetition_method: " + args.repetition_method);
  } else if (existing) {
    method = existing.method;
  } else {
    method = Task.RepetitionMethod.DueDate;
  }
  return new Task.RepetitionRule(args.repetition_rule, method);
}

function serializeTask(t, noteLimit) {
  const limit = noteLimit === undefined ? 500 : noteLimit;
  return {
    id: t.id.primaryKey,
    name: t.name,
    status: taskStatusName(t.taskStatus),
    flagged: t.flagged,
    in_inbox: t.inInbox,
    defer_date: iso(t.deferDate),
    due_date: iso(t.dueDate),
    completion_date: iso(t.completionDate),
    added: iso(t.added),
    modified: iso(t.modified),
    tags: t.tags.map(x => x.name),
    project_id: t.containingProject ? t.containingProject.id.primaryKey : null,
    project: t.containingProject ? t.containingProject.name : null,
    parent_id: t.parent ? t.parent.id.primaryKey : null,
    estimated_minutes: t.estimatedMinutes,
    has_repetition: serializeRepetition(t.repetitionRule) !== null,
    repetition: serializeRepetition(t.repetitionRule),
    note: t.note ? String(t.note).slice(0, limit) : null,
  };
}

function repetitionMethodName(m) {
  const names = new Map([
    [Task.RepetitionMethod.Fixed, "fixed"],
    [Task.RepetitionMethod.DueDate, "due_date"],
    [Task.RepetitionMethod.DeferUntilDate, "defer_until_date"],
    [Task.RepetitionMethod.None, "none"],
  ]);
  return names.get(m) || "none";
}

function repetitionMethodFromName(name) {
  const methods = new Map([
    ["fixed", Task.RepetitionMethod.Fixed],
    ["due_date", Task.RepetitionMethod.DueDate],
    ["defer_until_date", Task.RepetitionMethod.DeferUntilDate],
  ]);
  return methods.get(name);
}

function serializeRepetition(rule) {
  if (!rule) return null;
  const method = repetitionMethodName(rule.method);
  // A rule whose method is None does not actually repeat — report no repetition.
  if (method === "none") return null;
  return { rule: rule.ruleString, method: method };
}

function serializeProject(p) {
  return {
    id: p.id.primaryKey,
    name: p.name,
    status: projectStatusName(p.status),
    folder_id: p.parentFolder ? p.parentFolder.id.primaryKey : null,
    folder: p.parentFolder ? p.parentFolder.name : null,
    sequential: p.sequential,
    contains_singleton_actions: p.containsSingletonActions,
    defer_date: iso(p.deferDate),
    due_date: iso(p.dueDate),
    task_count: p.flattenedTasks.length,
    note: p.note ? String(p.note).slice(0, 500) : null,
  };
}

function serializeFolder(f) {
  return {
    id: f.id.primaryKey,
    name: f.name,
    parent_id: f.parent ? f.parent.id.primaryKey : null,
    status: f.status === Folder.Status.Dropped ? "dropped" : "active",
  };
}

function serializeTag(t) {
  return {
    id: t.id.primaryKey,
    name: t.name,
    parent_id: t.parent ? t.parent.id.primaryKey : null,
  };
}
