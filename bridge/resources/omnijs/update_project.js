const p = Project.byIdentifier(ARGS.id);
if (!p) return fail("not_found", "No project with id " + ARGS.id);
if (ARGS.name !== undefined && ARGS.name !== null) p.name = ARGS.name;
if (ARGS.note !== undefined && ARGS.note !== null) p.note = ARGS.note;
if (ARGS.sequential !== undefined && ARGS.sequential !== null) p.sequential = ARGS.sequential;
if (ARGS.due !== undefined) p.dueDate = ARGS.due ? new Date(ARGS.due) : null;
if (ARGS.defer !== undefined) p.deferDate = ARGS.defer ? new Date(ARGS.defer) : null;
if (ARGS.status) p.status = projectStatusFromName(ARGS.status);
if (ARGS.folder_id) {
  const f = Folder.byIdentifier(ARGS.folder_id);
  if (!f) return fail("not_found", "No folder with id " + ARGS.folder_id);
  moveSections([p], f.ending);
}
return ok({ project: serializeProject(p) });
