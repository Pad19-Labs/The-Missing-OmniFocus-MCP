return ok({
  counts: {
    inbox: inbox.length,
    projects: flattenedProjects.length,
    tasks: flattenedTasks.length,
    tags: flattenedTags.length,
    folders: flattenedFolders.length,
  },
  folders: flattenedFolders.map(serializeFolder),
  tags: flattenedTags.map(serializeTag),
  projects: flattenedProjects.map(p => ({
    id: p.id.primaryKey,
    name: p.name,
    status: projectStatusName(p.status),
    folder_id: p.parentFolder ? p.parentFolder.id.primaryKey : null,
    task_count: p.flattenedTasks.length,
  })),
});
