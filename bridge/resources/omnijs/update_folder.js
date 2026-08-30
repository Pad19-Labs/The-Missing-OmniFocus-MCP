const f = Folder.byIdentifier(ARGS.id);
if (!f) return fail("not_found", "No folder with id " + ARGS.id);

// Validate the destination before renaming, so a bad parent leaves the
// folder untouched rather than renamed-but-not-moved.
let destinationParent = null;
if (ARGS.parent_folder_id) {
  destinationParent = Folder.byIdentifier(ARGS.parent_folder_id);
  if (!destinationParent) return fail("not_found", "No folder with id " + ARGS.parent_folder_id);
}

if (ARGS.name !== undefined && ARGS.name !== null) f.name = ARGS.name;
if (destinationParent) moveSections([f], destinationParent.ending);
return ok({ folder: serializeFolder(f) });
