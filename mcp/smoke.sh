#!/usr/bin/env bash
#
# Manual smoke test for the web-framework MCP server.
# Drives the server over stdio with a sequence of JSON-RPC calls and prints
# the result of each tool call so you can eyeball the behaviour end to end.
#
# Usage:
#   FW_API_BASE_URL=http://localhost/proyectos-web/web-framework/api \
#   FW_API_KEY=your-api-key \
#   ./smoke.sh
#
# Optional:
#   REG_MODULE_ID   id_module of a real, existing module used by the deny-list
#                   bypass regression case (default 6).
#
# Requires: a built server (npm run build) and a running framework instance.

set -euo pipefail

cd "$(dirname "$0")"

: "${FW_API_BASE_URL:?Set FW_API_BASE_URL (e.g. http://localhost/proyectos-web/web-framework/api)}"
: "${FW_API_KEY:?Set FW_API_KEY (matches api/config.php -> api.key)}"
REG_MODULE_ID="${REG_MODULE_ID:-6}"

if [[ ! -f dist/index.js ]]; then
  echo "dist/index.js not found — run 'npm run build' first." >&2
  exit 1
fi

# Unique temp file for the server's stderr — avoids the symlink/permission
# pitfalls of a hardcoded path in a shared /tmp (CWE-377). Left on disk on
# purpose so it can be inspected after a failing run.
STDERR_LOG="$(mktemp "${TMPDIR:-/tmp}/mcp-smoke.XXXXXX")"

# Tracks whether any case failed, so the script can exit non-zero instead of
# silently "passing" on empty/missing responses.
FAILED=0

# JSON-RPC request helpers ---------------------------------------------------
# initialize + initialized handshake is required before any tools/call.
req_init='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"smoke","version":"0"}}}'
req_initd='{"jsonrpc":"2.0","method":"notifications/initialized"}'

# Each entry: id|label|method|params
declare -a CASES=(
  '2|tools/list (expect 6 tools)|tools/list|{}'
  '3|list_tables|tools/call|{"name":"list_tables","arguments":{}}'
  '4|list_pages|tools/call|{"name":"list_pages","arguments":{}}'
  '5|describe_table by suffix (admin)|tools/call|{"name":"describe_table","arguments":{"suffix":"admin"}}'
  '6|search_records DENY-LIST (admins -> must reject)|tools/call|{"name":"search_records","arguments":{"table":"admins"}}'
  '7|search_records invalid identifier (must reject via Zod)|tools/call|{"name":"search_records","arguments":{"table":"DROP TABLE pages;"}}'
  '8|search_records no matches (empty -> count:0, not error)|tools/call|{"name":"search_records","arguments":{"table":"pages","linkTo":"id_page","equalTo":99999}}'
  '9|get_record missing PK (found:false, not error)|tools/call|{"name":"get_record","arguments":{"table":"pages","id_column":"id_page","id":99999}}'
  '10|read_page id_page=8 (page + modules)|tools/call|{"name":"read_page","arguments":{"id_page":8}}'
  '11|resources/list (expect 9 docs)|resources/list|{}'
  '12|read framework://docs/SEGURIDAD|resources/read|{"uri":"framework://docs/SEGURIDAD"}'
  # Regression for the describe_table deny-list bypass (fixed in PR #48):
  # passing a real id_module together with a NON-matching suffix used to skip
  # both resolution branches and validate the client-supplied suffix instead of
  # the DB one, leaking the schema of a denied module. It must now be rejected.
  # Kept last (id 14) so the printed log stays in ascending order.
  "14|describe_table BYPASS REGRESSION (id_module=${REG_MODULE_ID} + mismatched suffix -> must reject)|tools/call|{\"name\":\"describe_table\",\"arguments\":{\"id_module\":${REG_MODULE_ID},\"suffix\":\"zzz_not_real\"}}"
)

# Build the full request stream once and pipe it to a single server process.
build_stream() {
  printf '%s\n' "$req_init" "$req_initd"
  for c in "${CASES[@]}"; do
    IFS='|' read -r id _label method params <<<"$c"
    printf '{"jsonrpc":"2.0","id":%s,"method":"%s","params":%s}\n' "$id" "$method" "$params"
  done
}

echo "▶ Running MCP smoke test against: $FW_API_BASE_URL"
echo

# Run the server, collect all JSON-RPC responses (one per line on stdout).
raw="$( { build_stream; sleep 2; } | node dist/index.js 2>"$STDERR_LOG" || true )"

# Select the JSON-RPC response whose top-level `id` equals the argument.
# Parses each stdout line with Node (matching the parsed `.id`) instead of a
# textual grep, so a payload that happens to contain `"id":N` — e.g. JSON
# embedded in a tool result — can never be picked by mistake.
pick_resp() {
  # Pass the wanted id via env var, not argv: `node -e` argv indexing varies
  # across Node versions, while process.env is unambiguous everywhere.
  printf '%s\n' "$raw" | PICK_ID="$1" node -e '
    const want = Number(process.env.PICK_ID);
    let s = "";
    process.stdin.on("data", (d) => (s += d)).on("end", () => {
      for (const line of s.split("\n")) {
        if (!line.trim()) continue;
        try {
          const m = JSON.parse(line);
          if (m && m.id === want) { process.stdout.write(line); return; }
        } catch { /* not a JSON-RPC line */ }
      }
    });'
}

# Pretty-print each labelled case by matching its response id.
print_case() {
  local id="$1" label="$2"
  echo "──────────────────────────────────────────────────────────────"
  echo "▌ [$id] $label"
  echo "──────────────────────────────────────────────────────────────"
  local line
  line="$(pick_resp "$id" || true)"
  if [[ -z "$line" ]]; then
    echo "  ✗ FAIL: no response (server may have errored; see $STDERR_LOG)"
    FAILED=1
    return
  fi
  # tools/call results carry text content; unwrap it for readability, else raw.
  if printf '%s' "$line" | node -e '
      let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{
        try{
          const m=JSON.parse(s);
          if(m.result&&m.result.content&&m.result.content[0]&&m.result.content[0].text){
            const isErr=m.result.isError?" (isError:true)":"";
            console.log("  result"+isErr+":");
            console.log(m.result.content[0].text.split("\n").map(l=>"    "+l).join("\n"));
          } else if (m.result){
            console.log("  result: "+JSON.stringify(m.result).slice(0,4000));
          } else if (m.error){
            console.log("  error: "+JSON.stringify(m.error));
          } else { console.log("  "+s); }
        }catch(e){ process.exit(1); }
      });' 2>/dev/null; then
    :
  else
    echo "  $line"
  fi
  echo
}

for c in "${CASES[@]}"; do
  IFS='|' read -r id label _method _params <<<"$c"
  # Special-case tools/list and resources/list: just count entries.
  case "$id" in
    2)
      echo "──────────────────────────────────────────────────────────────"
      echo "▌ [2] $label"
      echo "──────────────────────────────────────────────────────────────"
      # Parse the JSON-RPC response with Node rather than counting with grep/wc,
      # which would miscount if a tool description happened to contain "name":.
      # Exit non-zero on an empty/missing response so a crashed server fails the
      # test loudly instead of printing "0" and passing.
      if pick_resp 2 | node -e '
        let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{
          const tools=((JSON.parse(s||"{}").result)||{}).tools||[];
          if(tools.length===0){console.log("  ✗ FAIL: no tools advertised (empty/missing response)");process.exit(1);}
          console.log("  tools advertised: "+tools.length);
          tools.forEach((t)=>console.log("    - "+t.name));
        });'; then :; else FAILED=1; fi
      echo
      ;;
    11)
      echo "──────────────────────────────────────────────────────────────"
      echo "▌ [11] $label"
      echo "──────────────────────────────────────────────────────────────"
      if pick_resp 11 | node -e '
        let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{
          const resources=((JSON.parse(s||"{}").result)||{}).resources||[];
          if(resources.length===0){console.log("  ✗ FAIL: no resources advertised (empty/missing response)");process.exit(1);}
          console.log("  resources advertised: "+resources.length);
        });'; then :; else FAILED=1; fi
      echo
      ;;
    *)
      print_case "$id" "$label"
      ;;
  esac
done

if [[ "$FAILED" -ne 0 ]]; then
  echo "✗ Smoke test FAILED — see messages above and $STDERR_LOG"
  exit 1
fi

echo "✔ Done. Server stderr (if any) in $STDERR_LOG"
