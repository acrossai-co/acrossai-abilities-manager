# Feature Specification: Block Tree Mutation & Nested Editing

**Feature Branch**: `066-block-tree-mutation-and-nested-editing`
**Created**: 2026-08-12
**Status**: Draft
**Input**: User description: "Add six new abilities and enhance one existing ability to give clients complete read/write control over the Gutenberg block tree inside a WordPress post, including nested blocks. Today the plugin can inspect the block registry and edit a single top-level block, but cannot read a post's block tree, insert/remove/move/duplicate blocks, or insert a pattern at a position — and existing update-post-block explicitly defers nested-block editing. Realistic block-editor content nests blocks inside containers, so this gap blocks most practical automation."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Read a post's full block tree with addressable paths (Priority: P1)

A client (human operator or automation) needs a machine-readable view of every block inside a post — including nested blocks inside containers (columns, group, cover, query-loop) — with a canonical address for each block so subsequent operations can target any node unambiguously.

**Why this priority**: Every write operation in this feature requires first reading the current tree to know what to target. Without this, no downstream automation is possible. This is the foundational read primitive.

**Independent Test**: Create a post whose content contains a container block with two nested paragraph blocks. Invoke the read ability with that post's ID. Verify the response returns a hierarchical structure that includes every block, each annotated with a canonical path identifier (an array of zero-based child indices) that resolves to that block's position in the tree.

**Acceptance Scenarios**:

1. **Given** a post with a container block that contains two paragraphs, **When** the client requests the block tree for that post, **Then** the response contains the container at path `[0]` and its two children at paths `[0, 0]` and `[0, 1]`.
2. **Given** a post whose content is empty, **When** the client requests the block tree, **Then** the response returns an empty tree and a success indicator (not an error).
3. **Given** a post ID that does not exist, **When** the client requests the block tree, **Then** the response returns a failure indicator with a message identifying the missing post.

---

### User Story 2 — Insert a new block at any position in the tree (Priority: P1)

A client needs to add a new block anywhere in a post — either as a top-level block or nested inside an existing container — by pointing at the parent and the desired sibling index.

**Why this priority**: Insertion is the most-requested write operation for content authoring. Without nested insertion, clients cannot build realistic multi-column or container-based layouts.

**Independent Test**: Read the tree of a post that contains a container with one child. Invoke the insert ability with a target parent path pointing at the container and a sibling index of 0, providing a new block payload. Re-read the tree and verify the new block appears at the expected path and the previously-existing child has shifted to the next sibling index.

**Acceptance Scenarios**:

1. **Given** a post with a container at path `[0]` containing one child, **When** the client inserts a new block at parent path `[0]` index `0`, **Then** the new block occupies path `[0, 0]` and the prior child moves to `[0, 1]`.
2. **Given** a post with three top-level blocks, **When** the client inserts a new block at parent path `[]` (root) index `1`, **Then** the new block occupies path `[1]` and the tree grows to four top-level blocks.
3. **Given** the client provides a block name that does not match the required namespace/name format, **When** the insert is attempted, **Then** the response returns a failure with a message identifying the invalid block name.

---

### User Story 3 — Remove a block from any position in the tree (Priority: P1)

A client needs to delete a block anywhere in a post's tree — top-level or nested — by pointing at the block's canonical path.

**Why this priority**: Deletion pairs with insertion as a foundational tree-mutation primitive. Without it, clients cannot correct their own inserts or clean up existing content.

**Independent Test**: Read the tree of a post with nested blocks. Invoke the remove ability with a path targeting a nested child. Re-read the tree and verify only that block was removed and its parent + siblings remain intact.

**Acceptance Scenarios**:

1. **Given** a post with a container containing two children, **When** the client removes the block at path `[0, 0]`, **Then** the container retains one child and it is the former second child (now at `[0, 0]`).
2. **Given** the client requests removal at a path whose parent does not exist, **When** the remove is attempted, **Then** the response returns a failure with a message identifying the invalid path.

---

### User Story 4 — Update block attributes and inner HTML at any nesting depth (Priority: P1)

A client needs to update the attributes or inner content of a specific block at any nesting depth in a post — extending the existing top-level-only update capability to nested blocks.

**Why this priority**: The existing update capability is limited to top-level blocks. Realistic content nests blocks inside containers, so the current limitation blocks most practical editing scenarios. Extending this without breaking existing consumers is a foundational parity requirement.

**Independent Test**: Read the tree of a post with a nested paragraph inside a container. Invoke the update ability with the nested path and new attributes. Re-read the tree and verify only the targeted block changed; siblings and other subtrees are untouched.

**Acceptance Scenarios**:

1. **Given** a post with a container containing a paragraph at path `[0, 0]`, **When** the client updates that path with new attributes, **Then** the paragraph reflects the new attributes and no other block changes.
2. **Given** an existing consumer that continues to call the update ability with only the top-level index (no path), **When** the update executes, **Then** it behaves identically to prior versions of the ability with no behavior change.
3. **Given** the client provides attributes that fail validation against the registered block type's schema, **When** the update is attempted, **Then** the response returns a failure with a message identifying the invalid attribute.

---

### User Story 5 — Move a block from one position to another (Priority: P2)

A client needs to reorder blocks or reparent them (move a top-level block into a container, or move a nested block out) by specifying a source path and a destination.

**Why this priority**: Move rounds out the tree-mutation primitives beyond insert/remove/update. Useful for layout adjustments but not blocking initial parity — clients can achieve the same effect with remove-then-insert as a temporary workaround.

**Independent Test**: Read a post's tree containing two top-level blocks. Invoke the move ability from path `[1]` to a nested position inside the first block. Re-read the tree and verify the block moved atomically (no intermediate state where it appears twice or is missing).

**Acceptance Scenarios**:

1. **Given** two top-level blocks, **When** the client moves the block at path `[1]` into path `[0]` at index `0`, **Then** the source position is gone and the block appears as the first child of the former top-level `[0]`.
2. **Given** a container with three children, **When** the client moves the child at path `[0, 2]` to path `[0, 0]`, **Then** the child appears first and the other children shift down.

---

### User Story 6 — Duplicate a block in place (Priority: P2)

A client needs to clone an existing block (with its full subtree of inner blocks) and insert the clone as the next sibling of the source, so recurring layout patterns can be reused without re-authoring.

**Why this priority**: Duplication is a productivity primitive. Not blocking parity because clients could achieve the same via read + insert of the same payload.

**Independent Test**: Read a post's tree containing a container with one child. Invoke the duplicate ability at path `[0]`. Re-read the tree and verify a deep clone of the container (including its child) now sits at path `[1]`.

**Acceptance Scenarios**:

1. **Given** a container block with two nested children, **When** the client duplicates path `[0]`, **Then** a deep-cloned container with two nested children appears at `[1]` and the original is unchanged.
2. **Given** a nested block at path `[0, 1]`, **When** the client duplicates it, **Then** a clone appears at `[0, 2]` and subsequent siblings shift accordingly.

---

### User Story 7 — Insert a saved pattern at any position (Priority: P3)

A client needs to insert the contents of a saved block pattern (resolved by slug across database, active theme, and installed plugins) at any position in a post's tree, expanding the pattern into its component blocks in place.

**Why this priority**: Convenience feature that composes existing capabilities (pattern resolution + tree insertion). Not blocking because clients can manually assemble the equivalent blocks via multiple insert calls.

**Independent Test**: Given a known pattern slug that exists in the theme, invoke the insert-pattern ability at a target path. Re-read the tree and verify the pattern's constituent blocks appear at the target location in the same order as they exist in the pattern's definition.

**Acceptance Scenarios**:

1. **Given** a pattern slug present in the active theme's patterns directory, **When** the client inserts that pattern at parent path `[0]` index `0`, **Then** the pattern's blocks appear as children of `[0]` starting at index `0`.
2. **Given** a pattern slug that resolves in multiple sources (database + theme), **When** the client inserts without specifying a source, **Then** the response returns a failure identifying the ambiguity and the client can retry with an explicit source.
3. **Given** a pattern slug that does not resolve in any source, **When** the client inserts, **Then** the response returns a failure identifying the unknown slug.

---

### Edge Cases

- **Empty tree**: reading, and any attempt to address a non-empty path, on an empty tree — read succeeds; writes fail with a clear "path does not exist" message.
- **Out-of-range sibling index**: an insert at index greater than the current sibling count — append at the end rather than fail.
- **Classic / freeform content**: posts whose content includes `core/freeform` blocks (from the classic editor) — round-trip preserves the freeform content unchanged; reads expose it as a normal block node.
- **Post types not compatible with the block editor**: internal post types (revisions, nav menu items, custom CSS, customize changesets, oembed cache, user requests) — all abilities refuse to operate on these post types.
- **Unknown / unregistered block types**: a block whose type is not registered on the current site — reads still expose the block by name; attribute-schema validation degrades gracefully (warns but does not hard-fail) so writes remain possible for custom blocks.
- **Concurrent edits**: two clients editing the same post's tree at the same time — last write wins; there is no built-in optimistic-lock. (Explicit non-goal for this feature.)
- **Move to descendant**: a client attempting to move a block into its own subtree — refused with a failure message identifying the invalid destination.
- **Path overflow when duplicating in a nested position**: duplicating a block whose parent list is at capacity — succeeds; there is no artificial cap on sibling count.
- **Backward compatibility on update**: existing consumers of the update ability that pass only a top-level index (not a path) — behavior is unchanged.

## Requirements *(mandatory)*

### Functional Requirements

**Tree read**

- **FR-001**: The system MUST provide an ability to return the parsed block tree of a specified post as a hierarchical structure.
- **FR-002**: Each block in the returned tree MUST carry a canonical path identifier — an array of zero-based child indices — representing that block's position in the tree.
- **FR-003**: The returned structure MUST expose, for each block: block name, attributes, inner HTML, and its list of inner (nested) blocks.

**Canonical addressing**

- **FR-004**: All tree-mutation abilities MUST accept and interpret block positions using the canonical path format: an ordered array of zero-based non-negative integers. `[]` denotes the root (top-level list); `[i]` denotes the i-th top-level block; `[i, j]` denotes the j-th child of the i-th top-level block; and so on.
- **FR-005**: The system MUST reject any path that does not resolve to an existing block (except where the operation is defined to accept "one past the end" as an append position).

**Insert**

- **FR-006**: The system MUST provide an ability to insert a new block at a specified parent path and sibling index. If the sibling index equals or exceeds the current sibling count, the new block MUST be appended at the end.
- **FR-007**: The insert ability MUST accept a block payload composed of block name, attributes, inner HTML, and (optionally) a list of inner blocks.
- **FR-008**: The insert ability MUST validate the block name against the required namespace/name format before mutating the post.

**Remove**

- **FR-009**: The system MUST provide an ability to remove the block at a specified path.

**Update**

- **FR-010**: The existing update ability MUST accept an optional path input that identifies a block at any nesting depth.
- **FR-011**: When the path input is present, the update ability MUST apply the requested attribute and/or inner-HTML changes only to the block at that path, leaving all other blocks untouched.
- **FR-012**: When the path input is absent, the update ability MUST behave identically to its prior versions (using its existing top-level index or block-name + occurrence inputs) with no observable behavior change.

**Move**

- **FR-013**: The system MUST provide an ability to move a block from a source path to a destination (parent path + sibling index) atomically — the operation MUST NOT produce an intermediate state where the block appears twice or is missing.
- **FR-014**: The move ability MUST refuse operations whose destination lies inside the source's own subtree, returning a failure with a clear message.

**Duplicate**

- **FR-015**: The system MUST provide an ability to create a deep clone of the block at a specified path (including all of its inner blocks) and insert the clone as the next sibling of the source.

**Insert pattern**

- **FR-016**: The system MUST provide an ability to resolve a saved block pattern by slug (across database, active theme, and installed plugins) and insert its constituent blocks at a specified parent path and sibling index.
- **FR-017**: When a pattern slug is ambiguous across sources, the insert-pattern ability MUST refuse the operation and return a failure message identifying the ambiguity; the client MUST be able to disambiguate by specifying a source explicitly.

**Cross-cutting**

- **FR-018**: Every write ability (insert, remove, update, move, duplicate, insert-pattern) MUST require the caller to have the same per-post editing capability as the existing update ability, plus any additional global capability check the existing update ability enforces.
- **FR-019**: Every write ability MUST refuse to operate on post types that are internal-only or that do not support the block editor (revisions, nav menu items, custom CSS, customize changesets, oembed cache, user requests, and any post type whose registration excludes editor UI/REST exposure).
- **FR-020**: Every write ability MUST validate block attributes against the registered block type's schema when the block type is known; validation MUST degrade gracefully when the block type is not registered (log/warn but do not fail).
- **FR-021**: All abilities MUST return a response shape consistent with existing abilities — a `success` flag, a payload, and a human-readable message.
- **FR-022**: All write abilities MUST preserve round-trip fidelity of post content: reading, then writing back unchanged, MUST produce byte-identical stored content wherever the underlying parse-and-serialize round-trip permits.
- **FR-023**: All new abilities MUST live under the existing content category and namespace already used by the current update ability.

### Key Entities

- **Post**: The WordPress post whose content is being read or mutated. Identified by post ID.
- **Block**: A node in the post's content tree. Composed of: name (namespace/name), attributes (structured data), inner HTML (rendered markup), and inner blocks (ordered list of child Block nodes).
- **Block path**: An ordered list of zero-based integers that identifies a Block's position in a Post's tree. Empty list denotes the root.
- **Block pattern**: A named collection of blocks resolvable by slug from one of three sources — the database (reusable-block posts), the active theme's patterns directory, or an installed plugin's patterns directory.
- **Block type**: The registered definition for a given block name — includes its expected attribute schema. Discovered at runtime from the site's block registry.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A client can read any post's full block tree, including all nesting levels, in a single call — 100% of blocks reachable by path.
- **SC-002**: A client can perform any of the six tree-mutation operations (insert, remove, update, move, duplicate, insert-pattern) at any depth in the tree using a single canonical path input — no operation requires more than one call to affect one block.
- **SC-003**: Existing consumers of the update ability continue to function unchanged — 100% of the prior input shapes produce byte-identical outcomes to prior versions.
- **SC-004**: A round-trip of read → write-unchanged preserves post content byte-for-byte in at least 95% of realistic test posts (allowance for parser-normalization edge cases outside our control).
- **SC-005**: Every write ability refuses invalid inputs (bad block name, bad path, incompatible post type) with a clear, actionable failure message before mutating any state — 0 partial writes.
- **SC-006**: A client can build a two-column layout containing four total nested blocks from an empty post using no more than seven ability calls (1 read + 6 writes).

## Assumptions

- Clients speak to the plugin via the WordPress Abilities API — no new transport is introduced.
- The post's `post_content` is either block-editor content or content already round-trippable via WordPress's core block parser. Purely-classic (unparsed) content is not a target scenario for tree mutation but MUST survive unchanged when read.
- Registered block types on the target site accurately reflect the blocks used in real content. Where a block name is not registered (custom or removed plugin), attribute-schema validation is best-effort only.
- Pattern-source resolution reuses whatever helper the existing read-block-pattern ability uses — no new pattern-source discovery is introduced by this feature.
- Concurrent-write safety is out of scope for this feature; last-write-wins is acceptable and matches existing WordPress editing behavior.
- No user interface changes are required for this feature — abilities are consumed by clients (Abilities API), not by admin screens.
- Registration of new block types programmatically is explicitly out of scope; block-type authoring remains a plugin-code concern.
- Classic (freeform) block content transformation beyond passthrough (preserving it round-trip) is explicitly out of scope.
