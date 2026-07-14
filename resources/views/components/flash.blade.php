@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700 border border-green-200">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 border border-red-200">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 border border-red-200">
        <ul class="list-disc ps-5 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
