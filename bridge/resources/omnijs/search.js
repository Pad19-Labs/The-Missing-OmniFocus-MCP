const query = String(ARGS.query || "").toLowerCase();
const matches = [];
for (const t of flattenedTasks) {
  const inName = t.name.toLowerCase().includes(query);
  const inNote = t.note ? String(t.note).toLowerCase().includes(query) : false;
  if (inName || inNote) matches.push(t);
}
return ok({
  total: matches.length,
  tasks: matches.slice(0, ARGS.limit || 20).map(t => serializeTask(t)),
});
