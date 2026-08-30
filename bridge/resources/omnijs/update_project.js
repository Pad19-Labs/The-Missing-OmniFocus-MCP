const p = Project.byIdentifier(ARGS.id);
if (!p) return fail("not_found", "No project with id " + ARGS.id);

// Validate EVERYTHING before mutating anything — OmniFocus has no rollback,
// so a bad reference must not leave the project half-changed (especially not
// marked done, which completes all its incomplete tasks).
let destinationFolder = null;
if (ARGS.folder_id) {
  destinationFolder = Folder.byIdentifier(ARGS.folder_id);
  if (!destinationFolder) return fail("not_found", "No folder with id " + ARGS.folder_id);
}
let newStatus = undefined;
if (ARGS.status) {
  newStatus = projectStatusFromName(ARGS.status);
  if (newStatus === undefined) return fail("invalid_arguments", "Unknown project status: " + ARGS.status);
}
if (ARGS.due !== undefined && ARGS.due !== null && isNaN(new Date(ARGS.due).getTime())) {
  return fail("invalid_arguments", "Invalid due date: " + ARGS.due);
}
if (ARGS.defer !== undefined && ARGS.defer !== null && isNaN(new Date(ARGS.defer).getTime())) {
  return fail("invalid_arguments", "Invalid defer date: " + ARGS.defer);
}

// All validated — now apply.
if (ARGS.name !== undefined && ARGS.name !== null) p.name = ARGS.name;
if (ARGS.note !== undefined && ARGS.note !== null) p.note = ARGS.note;
if (ARGS.sequential !== undefined && ARGS.sequential !== null) p.sequential = ARGS.sequential;
if (ARGS.due !== undefined) p.dueDate = ARGS.due ? new Date(ARGS.due) : null;
if (ARGS.defer !== undefined) p.deferDate = ARGS.defer ? new Date(ARGS.defer) : null;
if (destinationFolder) moveSections([p], destinationFolder.ending);
if (newStatus !== undefined) p.status = newStatus;
return ok({ project: serializeProject(p) });
