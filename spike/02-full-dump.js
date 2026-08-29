(() => {
  const iso = d => (d ? d.toISOString() : null);

  const taskData = t => ({
    id: t.id.primaryKey,
    name: t.name,
    status: String(t.taskStatus),
    flagged: t.flagged,
    inInbox: t.inInbox,
    deferDate: iso(t.deferDate),
    dueDate: iso(t.dueDate),
    completionDate: iso(t.completionDate),
    added: iso(t.added),
    modified: iso(t.modified),
    tags: t.tags.map(tag => tag.name),
    project: t.containingProject ? t.containingProject.name : null,
    parent: t.parent ? t.parent.id.primaryKey : null,
    estimatedMinutes: t.estimatedMinutes,
    note: t.note ? t.note.slice(0, 500) : null,
  });

  const dump = {
    exportedAt: new Date().toISOString(),
    folders: flattenedFolders.map(f => ({
      id: f.id.primaryKey,
      name: f.name,
      parent: f.parent ? f.parent.name : null,
    })),
    tags: flattenedTags.map(tag => ({
      id: tag.id.primaryKey,
      name: tag.name,
      parent: tag.parent ? tag.parent.name : null,
    })),
    projects: flattenedProjects.map(p => ({
      id: p.id.primaryKey,
      name: p.name,
      status: String(p.status),
      folder: p.parentFolder ? p.parentFolder.name : null,
      sequential: p.sequential,
      dueDate: iso(p.dueDate),
      deferDate: iso(p.deferDate),
      taskCount: p.flattenedTasks.length,
    })),
    inbox: inbox.map(taskData),
    tasks: flattenedTasks.map(taskData),
  };
  return JSON.stringify(dump);
})()
