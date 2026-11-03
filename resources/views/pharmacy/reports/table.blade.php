<table class="table table-sm w-full">
    <thead class="bg-base-200">
        <tr>
            @foreach ($columns as $key => $label)
                <th scope="col" class="{{ \App\Services\Pharmacy\Reports\ReportDefinition::numeric($key) ? 'text-right' : 'text-left' }} whitespace-nowrap">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr class="border-b border-base-200 align-top">
                @foreach ($columns as $key => $label)
                    <td class="{{ \App\Services\Pharmacy\Reports\ReportDefinition::numeric($key) ? 'text-right tabular-nums whitespace-nowrap' : 'text-left' }} {{ $key === 'medicine' ? 'min-w-64' : '' }}">
                        @if (($row[$key] ?? null) === null)
                            <span aria-label="Unavailable">—</span>
                        @elseif (\App\Services\Pharmacy\Reports\ReportDefinition::numeric($key))
                            {{ number_format((float) $row[$key], $key === 'member_count' ? 0 : 2) }}
                        @else
                            {{ $row[$key] }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="py-12 text-center">No records match these filters. Try another date range, fund, or medicine.</td></tr>
        @endforelse
    </tbody>
    <tfoot class="bg-base-200 font-semibold">
        <tr>
            @foreach ($columns as $key => $label)
                <td class="{{ \App\Services\Pharmacy\Reports\ReportDefinition::numeric($key) ? 'text-right tabular-nums whitespace-nowrap' : 'text-left' }}">
                    @if ($loop->first)
                        Filtered totals
                    @elseif (array_key_exists($key, $totals))
                        {{ number_format((float) $totals[$key], 2) }}
                    @endif
                </td>
            @endforeach
        </tr>
    </tfoot>
</table>
