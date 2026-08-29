(() => {
  const ids = ["nQRIXZpigED", "fL7GiHha8vk"]; // subfolder (holds everything now), then the emptied original folder
  const deleted = [];
  for (const id of ids) {
    const f = Folder.byIdentifier(id);
    if (f) {
      deleted.push({ id, name: f.name });
      deleteObject(f);
    } else {
      deleted.push({ id, name: null });
    }
  }
  return JSON.stringify({ deleted });
})()
