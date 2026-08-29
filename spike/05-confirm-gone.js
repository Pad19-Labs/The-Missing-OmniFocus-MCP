(() => {
  let byId;
  try {
    byId = Task.byIdentifier("jW1Uu0dhLIA") !== null;
  } catch (e) {
    byId = "error: " + e.message;
  }
  const byName = inbox.filter(t => t.name.startsWith("omafocus spike")).length;
  return JSON.stringify({ foundById: byId, inboxMatchesByName: byName, inboxCount: inbox.length });
})()
