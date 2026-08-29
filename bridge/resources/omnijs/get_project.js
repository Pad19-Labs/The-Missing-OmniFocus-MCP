const p = Project.byIdentifier(ARGS.id);
if (!p) return fail("not_found", "No project with id " + ARGS.id);
return ok({
  project: serializeProject(p),
  tasks: p.flattenedTasks.map(t => serializeTask(t)),
});
