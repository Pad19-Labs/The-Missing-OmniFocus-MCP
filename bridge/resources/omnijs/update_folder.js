const f = Folder.byIdentifier(ARGS.id);
if (!f) return fail("not_found", "No folder with id " + ARGS.id);
if (ARGS.name !== undefined && ARGS.name !== null) f.name = ARGS.name;
if (ARGS.parent_folder_id) {
  const parent = Folder.byIdentifier(ARGS.parent_folder_id);
  if (!parent) return fail("not_found", "No folder with id " + ARGS.parent_folder_id);
  moveSections([f], parent.ending);
}
return ok({ folder: serializeFolder(f) });
