(() => {
  const r = {};

  // Create a folder at the library root, with a subfolder inside it
  const folder = new Folder("omafocus spike folder — safe to delete", library.ending);
  const sub = new Folder("omafocus spike subfolder", folder.ending);
  r.folderId = folder.id.primaryKey;
  r.subfolderId = sub.id.primaryKey;

  // Create a project inside the folder, then rename it
  const p = new Project("omafocus spike project", folder.ending);
  r.projectId = p.id.primaryKey;
  p.name = "omafocus spike project (renamed)";

  // Move the project into the subfolder
  moveSections([p], sub.beginning);
  r.projectParentAfterMove = p.parentFolder ? p.parentFolder.name : null;

  // Change project status and structure
  p.status = Project.Status.OnHold;
  p.sequential = true;
  r.projectStatus = String(p.status);
  r.projectSequential = p.sequential;

  // File an inbox task into the project
  const t1 = new Task("omafocus spike task — safe to delete");
  r.t1WasInInbox = t1.inInbox;
  moveTasks([t1], p.beginning);
  r.t1ProjectAfterMove = t1.containingProject ? t1.containingProject.name : null;
  r.t1InInboxAfterMove = t1.inInbox;

  // Promote an inbox task to a full project (the "idea → project" move)
  const t2 = new Task("omafocus spike idea — safe to delete");
  const converted = convertTasksToProjects([t2], sub.ending);
  r.convertedProjectId = converted[0].id.primaryKey;
  r.convertedProjectName = converted[0].name;

  // Move the whole subfolder (with its contents) up to the library root
  moveSections([sub], library.ending);
  r.subfolderParentAfterMove = sub.parent ? sub.parent.name : "library root";

  return JSON.stringify(r);
})()
