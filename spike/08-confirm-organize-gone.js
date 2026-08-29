(() => {
  const leftovers = {
    folders: flattenedFolders.filter(f => f.name.startsWith("omafocus spike")).length,
    projects: flattenedProjects.filter(p => p.name.startsWith("omafocus spike")).length,
    tasks: flattenedTasks.filter(t => t.name.startsWith("omafocus spike")).length,
    inbox: inbox.filter(t => t.name.startsWith("omafocus spike")).length,
  };
  return JSON.stringify(leftovers);
})()
