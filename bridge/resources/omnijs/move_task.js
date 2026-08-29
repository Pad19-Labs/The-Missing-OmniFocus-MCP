const t = Task.byIdentifier(ARGS.id);
if (!t) return fail("not_found", "No task with id " + ARGS.id);
if (ARGS.to_inbox) {
  moveTasks([t], inbox.ending);
} else if (ARGS.project_id) {
  const p = Project.byIdentifier(ARGS.project_id);
  if (!p) return fail("not_found", "No project with id " + ARGS.project_id);
  moveTasks([t], p.ending);
} else if (ARGS.parent_task_id) {
  const parent = Task.byIdentifier(ARGS.parent_task_id);
  if (!parent) return fail("not_found", "No task with id " + ARGS.parent_task_id);
  moveTasks([t], parent.ending);
} else {
  return fail("invalid_arguments", "Provide project_id, parent_task_id, or to_inbox.");
}
return ok({ task: serializeTask(t) });
