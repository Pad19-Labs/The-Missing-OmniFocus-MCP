const projects = [];
for (const p of flattenedProjects) {
  if (ARGS.status && projectStatusName(p.status) !== ARGS.status) continue;
  if (ARGS.folder_id && (!p.parentFolder || p.parentFolder.id.primaryKey !== ARGS.folder_id)) continue;
  projects.push(serializeProject(p));
}
return ok({ total: projects.length, projects: projects });
