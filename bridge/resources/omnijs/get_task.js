const t = Task.byIdentifier(ARGS.id);
if (!t) return fail("not_found", "No task with id " + ARGS.id);
return ok({
  task: serializeTask(t, 4000),
  children: t.children.map(c => serializeTask(c)),
});
