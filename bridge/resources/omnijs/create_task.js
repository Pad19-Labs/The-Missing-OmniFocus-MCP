let position = null;
if (ARGS.project_id) {
  const p = Project.byIdentifier(ARGS.project_id);
  if (!p) return fail("not_found", "No project with id " + ARGS.project_id);
  position = p.ending;
} else if (ARGS.parent_task_id) {
  const parent = Task.byIdentifier(ARGS.parent_task_id);
  if (!parent) return fail("not_found", "No task with id " + ARGS.parent_task_id);
  position = parent.ending;
}
const t = new Task(ARGS.name);
applyTaskFields(t, ARGS);
if (position) moveTasks([t], position);
return ok({ task: serializeTask(t) });
