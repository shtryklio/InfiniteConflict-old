<div class="content queue">
  <h1>Research Queue</h1>
    <table class="reseach" id="research-queue"{if !$queue} style="display:none;"{/if}>
      <thead>
        <tr>
          <th class="name">Name</th>
          <th class="turns">Turns</th>
          <th class="status">Status</th>
          <th class="remove">Remove</th>
        </tr>
      </thead>
      <tbody>
        {if $queue}
          {foreach from=$queue item=r name=queue}
            <tr>
              <td>{$r.name}</td>
              <td>{$r.turns}</td>
              <td>{if $r.started == 1}Started{else}{if $smarty.foreach.queue.first}Starting{else}Queued{/if}{/if}</td>
              <td class="remove"><a href="/ajax/research/queue/remove/{$r.hash}/">[x]</a></td>
            </tr>
          {/foreach}
        {/if}
      </tbody>
    </table>

  <div class="empty-queue"{if $queue} style="display:none"{/if}>
    <p>You do not have any research in the queue</p>
  </div>

</div>

<div class="content research">
  <form id="research-list" action="/ajax/research/queue/add/" method="post">
    <h1>Research</h1>
    {if $research}
      <table class="reseach">
        <thead>
          <tr>
            <th class="name">Name</th>
            <th class="turns">Turns</th>
            <th class="cost">Cost</th>
            <th class="queue"></th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$research item=r}
            <tr>
              <td class="name">{$r.name}</td>
              <td class="turns">{$r.turns}</td>
              <td class="cost">{$r.resources.10.cost}</td>
              <td><input type="radio" name="research" value="{$r.id}" /></td>
            </tr>
          {/foreach}
        </tbody>
      </table>
      
      <p><input type="submit" value="Queue" /></p>
    {/if}
  </form>
  <div class="clear"></div>
</div>