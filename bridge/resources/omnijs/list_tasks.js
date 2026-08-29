const dueBefore = ARGS.due_before ? new Date(ARGS.due_before) : null;
const matches = [];
for (const t of flattenedTasks) {
  if (ARGS.project_id && (!t.containingProject || t.containingProject.id.primaryKey !== ARGS.project_id)) continue;
  if (ARGS.tag && !t.tags.some(x => x.name === ARGS.tag)) continue;
  if (ARGS.status && taskStatusName(t.taskStatus) !== ARGS.status) continue;
  if (ARGS.flagged !== null && ARGS.flagged !== undefined && t.flagged !== ARGS.flagged) continue;
  if (dueBefore && (!t.dueDate || t.dueDate > dueBefore)) continue;
  matches.push(t);
}
return ok({
  total: matches.length,
  tasks: matches.slice(0, ARGS.limit || 50).map(t => serializeTask(t)),
});
