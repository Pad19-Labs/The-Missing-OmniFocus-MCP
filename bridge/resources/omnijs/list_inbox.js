const offset = ARGS.offset || 0;
const limit = ARGS.limit || 50;
return ok({
  total: inbox.length,
  tasks: inbox.slice(offset, offset + limit).map(t => serializeTask(t)),
});
