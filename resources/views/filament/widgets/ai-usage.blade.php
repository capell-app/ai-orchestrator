<div class="space-y-2">
    <div class="text-lg font-semibold">AI Usage</div>
    <div class="flex items-center space-x-6">
        <div>
            <div class="text-2xl font-bold">
                {{ number_format($generationCount) }}
            </div>
            <div class="text-sm text-gray-500">Generations</div>
        </div>
        <div>
            <div class="text-2xl font-bold">
                {{ number_format($totalTokens) }}
            </div>
            <div class="text-sm text-gray-500">Total Tokens</div>
        </div>
    </div>
</div>
