---
title: Checking the file
description: See where the links come from, and check them, with the debug:llms-txt command.
sidebar:
    order: 9
---

```bash
php bin/console debug:llms-txt
```

The command builds the document like a request of the file does, every listener and link provider included, and shows:

- **the sources** in the order they run, with their priority: the route options, the
  [link providers](/guides/link-providers/) with their section, the [event listeners](/guides/event-listener/);
- **the sections** in the order of the file, with their number of links;
- **the size** of `llms.txt`, and of `llms-full.txt` when [enabled](/guides/llms-full/);
- **the dumped files**, their size and date.

## Checks

| Check           | Warns when                                                                                           |
| --------------- | ---------------------------------------------------------------------------------------------------- |
| Title           | the document has no title                                                                            |
| Duplicates      | two links share a URL                                                                                |
| Absolute URLs   | a URL is relative, or not `http(s)`                                                                  |
| Host            | a URL is on another host than the router's (`framework.router.default_uri` in a command)             |
| Link titles     | a link has an empty title                                                                            |
| Empty sections  | a section has no links (it is left out of the file)                                                  |
| Size            | `llms.txt` weighs more than `--max-size`, 1 MiB by default: past that, it no longer fits most models |
| `llms-full.txt` | links have a content while `llms-full.txt` is disabled                                               |

Each check reports how many links fail it, and the first one.

## In a CI

```bash
php bin/console debug:llms-txt --strict                # fails on a warning
php bin/console debug:llms-txt --max-size=524288       # warns past 512 KiB
php bin/console debug:llms-txt --format=json           # sources, sections, sizes, files and warnings
```
