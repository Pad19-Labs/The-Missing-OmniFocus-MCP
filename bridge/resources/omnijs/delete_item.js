let obj = null;
let children = 0;
if (ARGS.type === "task") {
  obj = Task.byIdentifier(ARGS.id);
  if (obj) children = obj.children.length;
} else if (ARGS.type === "project") {
  obj = Project.byIdentifier(ARGS.id);
  if (obj) children = obj.flattenedTasks.length;
} else if (ARGS.type === "folder") {
  obj = Folder.byIdentifier(ARGS.id);
  if (obj) children = obj.projects.length + obj.folders.length;
} else {
  return fail("invalid_arguments", "type must be task, project, or folder");
}
if (!obj) return fail("not_found", "No " + ARGS.type + " with id " + ARGS.id);
if (children > 0 && !ARGS.confirm_cascade) {
  return fail(
    "cascade_confirmation_required",
    "The " + ARGS.type + " contains " + children + " item(s). Pass confirm_cascade to delete anyway."
  );
}
const info = { id: ARGS.id, type: ARGS.type, name: obj.name, children: children };
deleteObject(obj);
return ok(info);
