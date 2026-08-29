(() => {
  const summary = {
    counts: {
      inbox: inbox.length,
      projects: flattenedProjects.length,
      tasks: flattenedTasks.length,
      tags: flattenedTags.length,
      folders: flattenedFolders.length,
    },
    inboxSampleNames: inbox.slice(0, 3).map(t => t.name),
  };
  return JSON.stringify(summary);
})()
