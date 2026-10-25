<?php
namespace Tests\Feature;
use App\Models\{User, Task};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TaskFlowTest extends TestCase {
    use RefreshDatabase;
    public function test_flow() {
        $m = User::create(['name'=>'M','email'=>'m@x','role'=>'manager','password'=>'x']);
        $a = User::create(['name'=>'A','email'=>'a@x','role'=>'employee','password'=>'x']);
        $b = User::create(['name'=>'B','email'=>'b@x','role'=>'employee','password'=>'x']);
        $this->actingAs($m)->post('/tasks',['title'=>'T','priority'=>'high','due_date'=>now()->addDay()->toDateString(),'assignees'=>[$a->id],'scopes'=>[$a->id=>'front'],'reviewer_id'=>$b->id,'checklist'=>"x\ny"])->assertRedirect();
        $t = Task::first(); $as = $t->assignments()->first();
        $this->actingAs($m)->get('/')->assertOk();
        $this->actingAs($m)->get("/tasks/{$t->id}")->assertOk();
        $this->actingAs($a)->post("/tasks/{$t->id}/assignments/{$as->id}/start")->assertRedirect();
        $this->actingAs($a)->get('/')->assertOk();
        $this->actingAs($a)->get("/tasks/{$t->id}")->assertOk();
        $e = $a->timeEntries()->first();
        $this->actingAs($a)->post("/timer/stop/{$e->id}");
        $this->actingAs($a)->post("/tasks/{$t->id}/comments",['body'=>'done','assignment_id'=>$as->id])->assertRedirect();
        $this->actingAs($a)->post("/tasks/{$t->id}/assignments/{$as->id}/submit");
        $this->actingAs($b)->get("/tasks/{$t->id}")->assertOk();
        $this->actingAs($b)->get('/')->assertOk()->assertSee('На моём ревью');
        $this->actingAs($b)->post("/tasks/{$t->id}/assignments/{$as->id}/review",['decision'=>'rework','review_comment'=>'bad']);
        $this->assertEquals(1, $as->fresh()->rework_count);
        $this->actingAs($a)->post("/tasks/{$t->id}/assignments/{$as->id}/submit");
        $this->actingAs($b)->post("/tasks/{$t->id}/assignments/{$as->id}/review",['decision'=>'accepted','score'=>4])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals('done', $t->fresh()->status);
        foreach (['overview', 'people', 'time', 'deadlines', 'quality', 'tags'] as $tab) $this->actingAs($m)->get('/reports/'.$tab)->assertOk();
        $this->actingAs($m)->get('/reports/person/'.$a->id)->assertOk();
        foreach (['/tasks', '/tasks/create', '/team', '/tags', '/?priority=urgent&overdue=1'] as $url) $this->actingAs($m)->get($url)->assertOk();
        foreach (['active', 'queue', 'reviews', 'history'] as $tab) $this->actingAs($a)->get('/?tab='.$tab.'&priority=urgent')->assertOk();
        $this->actingAs($m)->post("/tasks/{$t->id}/assignments",['user_id'=>$b->id,'scope'=>'back']);
        $as2 = $t->assignments()->where('user_id',$b->id)->first();
        $c = User::create(['name'=>'C','email'=>'c@x','role'=>'employee','password'=>'x']);
        $this->actingAs($m)->post("/tasks/{$t->id}/assignments/{$as2->id}/reassign",['user_id'=>$c->id])->assertSessionHasNoErrors();
        $this->assertEquals($c->id, $as2->fresh()->user_id);
        $this->actingAs($m)->put("/tasks/{$t->id}",['title'=>'T2','priority'=>'low','checklist'=>'z'])->assertSessionHasNoErrors();
        $this->assertEquals(3, $t->checklist()->count());
        $this->actingAs($m)->delete("/tasks/{$t->id}/assignments/{$as2->id}");
        $this->actingAs($m)->get("/tasks/{$t->id}")->assertOk();
    }
}
