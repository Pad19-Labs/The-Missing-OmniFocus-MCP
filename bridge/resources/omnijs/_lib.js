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

function applyTaskFields(t, args) {
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
  if (args.repetition_rule !== undefined) {
    if (args.repetition_rule === null) {
      t.repetitionRule = null;
    } else {
      const method = args.repetition_method
        ? repetitionMethodFromName(args.repetition_method)
        : Task.RepetitionMethod.DueDate;
      t.repetitionRule = new Task.RepetitionRule(args.repetition_rule, method);
    }
  }
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
    has_repetition: t.repetitionRule !== null,
    repetition: serializeRepetition(t.repetitionRule),
    note: t.note ? String(t.note).slice(0, limit) : null,
  };
}

function repetitionMethodName(m) {
  const names = new Map([
    [Task.RepetitionMethod.Fixed, "fixed"],
    [Task.RepetitionMethod.DueDate, "due_date"],
    [Task.RepetitionMethod.DeferUntilDate, "defer_until_date"],
  ]);
  return names.get(m) || String(m);
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
  return { rule: rule.ruleString, method: repetitionMethodName(rule.method) };
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
