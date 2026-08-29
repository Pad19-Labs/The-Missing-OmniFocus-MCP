(() => {
  const t = new Task("omafocus spike — safe to delete");
  t.note = "Created by the omafocus write-path spike. Will be deleted immediately.";
  return JSON.stringify({
    id: t.id.primaryKey,
    name: t.name,
    inInbox: t.inInbox,
  });
})()
