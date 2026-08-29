const t = Task.byIdentifier(ARGS.id);
if (!t) return fail("not_found", "No task with id " + ARGS.id);
applyTaskFields(t, ARGS);
if (ARGS.status === "completed" && !t.completed) t.markComplete();
else if (ARGS.status === "dropped") t.drop(false);
else if (ARGS.status === "active") t.markIncomplete();
return ok({ task: serializeTask(t) });
