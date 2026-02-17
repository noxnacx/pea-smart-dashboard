<li class="node">
    <div>
        @php
            $bgClass = 'bg-gray';
            if($node->type == 'strategy') $bgClass = 'bg-strategy';
            elseif($node->type == 'plan') $bgClass = 'bg-plan';
            elseif($node->type == 'project') $bgClass = 'bg-project';
            elseif($node->type == 'task') $bgClass = 'bg-task';
        @endphp

        <span class="badge {{ $bgClass }}">{{ strtoupper($node->type) }}</span>
        <strong>{{ $node->name }}</strong>

        <span class="text-meta">
            [ <span class="progress">ความคืบหน้า: {{ $node->progress }}%</span> |
            สถานะ: {{ strtoupper($node->status) }}
            @if($node->projectManager) | PM: {{ $node->projectManager->name }} @endif
            ]
        </span>
    </div>

    @if($node->children && $node->children->count() > 0)
        <ul class="tree">
            @foreach($node->children as $child)
                @include('reports.partials.tree-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
