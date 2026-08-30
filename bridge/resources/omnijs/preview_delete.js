// Preview the true, recursive blast radius of a deletion — never deletes.
function countTaskDescendants(t) {
  let n = t.flattenedChildren ? t.flattenedChildren.length : t.children.length;
  return n;
}

let obj = null;
let descendants = 0;
if (ARGS.type === "task") {
  obj = Task.byIdentifier(ARGS.id);
  if (obj) descendants = countTaskDescendants(obj);
} else if (ARGS.type === "project") {
  obj = Project.byIdentifier(ARGS.id);
  if (obj) descendants = obj.flattenedTasks.length;
} else if (ARGS.type === "folder") {
  obj = Folder.byIdentifier(ARGS.id);
  if (obj) {
    // Every nested folder, project, and task — the real recursive count.
    descendants =
      obj.flattenedFolders.length +
      obj.flattenedProjects.length +
      obj.flattenedProjects.reduce(function (sum, p) { return sum + p.flattenedTasks.length; }, 0);
  }
} else {
  return fail("invalid_arguments", "type must be task, project, or folder");
}
if (!obj) return fail("not_found", "No " + ARGS.type + " with id " + ARGS.id);
return ok({ id: ARGS.id, type: ARGS.type, name: obj.name, descendants: descendants });
