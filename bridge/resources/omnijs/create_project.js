let position = library.ending;
if (ARGS.folder_id) {
  const f = Folder.byIdentifier(ARGS.folder_id);
  if (!f) return fail("not_found", "No folder with id " + ARGS.folder_id);
  position = f.ending;
}
const p = new Project(ARGS.name, position);
if (ARGS.note) p.note = ARGS.note;
if (ARGS.sequential !== undefined && ARGS.sequential !== null) p.sequential = ARGS.sequential;
if (ARGS.singleton) p.containsSingletonActions = true;
if (ARGS.status) p.status = projectStatusFromName(ARGS.status);
return ok({ project: serializeProject(p) });
