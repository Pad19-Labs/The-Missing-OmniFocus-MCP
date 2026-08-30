const t = Task.byIdentifier(ARGS.id);
if (!t) return fail("not_found", "No task with id " + ARGS.id);

// Validate + build the repetition rule BEFORE mutating any field, so a bad
// rule leaves the task unchanged rather than half-updated (H1).
const rule = buildRepetitionRule(ARGS, t.repetitionRule);

applyTaskFields(t, ARGS, rule);

// Status changes come last. For a repeating task, markComplete() resolves the
// current occurrence and returns the completed clone while `t` advances to the
// next occurrence — report both so the agent doesn't re-complete the same one (H2).
let completed = null;
let nextOccurrence = null;
if (ARGS.status === "completed" && !t.completed) {
  completed = t.markComplete();
  if (completed && completed.id.primaryKey !== t.id.primaryKey) {
    // Repeating task: t is now the next occurrence.
    nextOccurrence = t;
  }
} else if (ARGS.status === "dropped") {
  t.drop(false);
} else if (ARGS.status === "active") {
  t.markIncomplete();
}

const result = { task: serializeTask(completed || t) };
if (nextOccurrence) result.next_occurrence = serializeTask(nextOccurrence);
return ok(result);
