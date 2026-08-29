let position = library.ending;
if (ARGS.parent_folder_id) {
  const parent = Folder.byIdentifier(ARGS.parent_folder_id);
  if (!parent) return fail("not_found", "No folder with id " + ARGS.parent_folder_id);
  position = parent.ending;
}
const f = new Folder(ARGS.name, position);
return ok({ folder: serializeFolder(f) });
