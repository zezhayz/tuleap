<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\NotInComparison;

use CodendiDataAccess;
use Tracker_FormElement_Field;
use Tuleap\Tracker\Report\Query\Advanced\CollectionOfListValuesExtractor;
use Tuleap\Tracker\Report\Query\Advanced\FromWhere;
use Tuleap\Tracker\Report\Query\Advanced\Grammar\Comparison;
use Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\FromWhereNotEqualComparisonListFieldBindUgroupsBuilder;
use Tuleap\Tracker\Report\Query\Advanced\QueryBuilder\ListBindUgroupsFromWhereBuilder;
use Tuleap\Tracker\Report\Query\Advanced\UgroupLabelConverter;

class ForListBindUGroups implements ListBindUgroupsFromWhereBuilder
{
    /** @var  FromWhereNotEqualComparisonListFieldBindUgroupsBuilder */
    private $from_where_builder;
    /**
     * @var CollectionOfListValuesExtractor
     */
    private $values_extractor;
    /**
     * @var UgroupLabelConverter
     */
    private $label_converter;

    public function __construct(
        CollectionOfListValuesExtractor $values_extractor,
        FromWhereNotEqualComparisonListFieldBindUgroupsBuilder $from_where_builder,
        UgroupLabelConverter $label_converter
    ) {
        $this->from_where_builder = $from_where_builder;
        $this->values_extractor   = $values_extractor;
        $this->label_converter    = $label_converter;
    }

    /**
     * @return FromWhere
     */
    public function getFromWhere(Comparison $comparison, Tracker_FormElement_Field $field)
    {
        $suffix     = spl_object_hash($comparison);
        $values     = $this->values_extractor->extractCollectionOfValues($comparison->getValueWrapper(), $field);
        $field_id   = (int)$field->getId();
        $tracker_id = (int)$field->getTrackerId();

        $changeset_value_list_alias = "CVList_{$field_id}_{$suffix}";
        $changeset_value_alias      = "CV_{$field_id}_{$suffix}";
        $ugroup_alias               = "ListValue_{$field_id}_{$suffix}";
        $bind_value_alias           = "BindValue_{$field_id}_{$suffix}";
        $filter_alias               = "Filter_{$field_id}_{$suffix}";

        $normalized_values = array();
        foreach ($values as $value) {
            if ($this->label_converter->isASupportedDynamicUgroup($value)) {
                $value = $this->label_converter->convertLabelToTranslationKey($value);
            }

            $normalized_values[] = $value;
        }

        $escaped_values = $this->quoteSmartImplode($normalized_values);
        $condition      = "$ugroup_alias.name IN($escaped_values)";

        return $this->from_where_builder->getFromWhere(
            $field_id,
            $changeset_value_alias,
            $changeset_value_list_alias,
            $ugroup_alias,
            $bind_value_alias,
            $filter_alias,
            $tracker_id,
            $condition
        );
    }

    private function quoteSmartImplode($values)
    {
        return CodendiDataAccess::instance()->quoteSmartImplode(',', $values);
    }
}