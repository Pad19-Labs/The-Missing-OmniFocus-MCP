const t = Task.byIdentifier(ARGS.task_id);
if (!t) return fail("not_found", "No task with id " + ARGS.task_id);
let position = library.ending;
if (ARGS.folder_id) {
  const f = Folder.byIdentifier(ARGS.folder_id);
  if (!f) return fail("not_found", "No folder with id " + ARGS.folder_id);
  position = f.ending;
}
const p = convertTasksToProjects([t], position)[0];
if (ARGS.status) p.status = projectStatusFromName(ARGS.status);
return ok({ project: serializeProject(p) });
