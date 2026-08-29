(() => {
  const id = "jW1Uu0dhLIA";
  const t = Task.byIdentifier(id);
  if (!t) return JSON.stringify({ found: false, id });
  const info = { found: true, id, name: t.name, inInbox: t.inInbox };
  deleteObject(t);
  info.stillExistsAfterDelete = Task.byIdentifier(id) !== null;
  return JSON.stringify(info);
})()
