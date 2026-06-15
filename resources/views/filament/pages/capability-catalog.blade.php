<x-filament-panels::page>
    <x-filament::section>
        <div class="overflow-x-auto">
            <table
                class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10"
            >
                <thead>
                    <tr
                        class="text-left text-xs font-semibold text-gray-500 uppercase dark:text-gray-400"
                    >
                        <th class="px-3 py-2">
                            {{ __('capell-ai-orchestrator::package.catalog_module') }}
                        </th>
                        <th class="px-3 py-2">
                            {{ __('capell-ai-orchestrator::package.catalog_capability') }}
                        </th>
                        <th class="px-3 py-2">
                            {{ __('capell-ai-orchestrator::package.catalog_approval') }}
                        </th>
                        <th class="px-3 py-2">
                            {{ __('capell-ai-orchestrator::package.catalog_required_ability') }}
                        </th>
                        <th class="px-3 py-2">
                            {{ __('capell-ai-orchestrator::package.catalog_action') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($this->capabilities() as $capability)
                        <tr>
                            <td class="px-3 py-3 align-top">
                                <div
                                    class="font-medium text-gray-950 dark:text-white"
                                >
                                    {{ $capability['moduleLabel'] }}
                                </div>
                                <div
                                    class="font-mono text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ $capability['moduleKey'] }}
                                </div>
                            </td>
                            <td class="px-3 py-3 align-top">
                                <div
                                    class="font-medium text-gray-950 dark:text-white"
                                >
                                    {{ $capability['label'] }}
                                </div>
                                <div class="text-gray-600 dark:text-gray-300">
                                    {{ $capability['description'] }}
                                </div>
                                <div
                                    class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ $capability['key'] }}
                                </div>
                            </td>
                            <td
                                class="px-3 py-3 align-top font-mono text-xs text-gray-700 dark:text-gray-300"
                            >
                                {{ $capability['approvalLevel'] }}
                            </td>
                            <td
                                class="px-3 py-3 align-top font-mono text-xs text-gray-700 dark:text-gray-300"
                            >
                                {{ $capability['requiredAbility'] ?? __('capell-ai-orchestrator::package.catalog_no_required_ability') }}
                            </td>
                            <td
                                class="px-3 py-3 align-top font-mono text-xs text-gray-700 dark:text-gray-300"
                            >
                                {{ $capability['actionClass'] }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ __('capell-ai-orchestrator::package.catalog_empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
