<?php
/** Per-configuration presentation only: no price, inventory or persistence writes. */
class WatchysCartThumbnails
{
    public const DIRECTORY = 'cart-reference-v1';
    public static function key($model, $selection)
    {
        if (!is_array($selection) || count($selection)!==4) { return null; }
        $parts=[$model['id']];
        foreach (['case','dial','hands','strap'] as $group) {
            $id=$selection[$group]??null;
            if (!is_string($id) || !isset($model['options'][$group][$id])) { return null; }
            $parts[]=$id;
        }
        return hash('sha256',implode('|',$parts));
    }
    public static function decorate($products, $records, $catalog, $exists, $url)
    {
        $models=[]; foreach ($catalog->models() as $model) { $models[$model['id']]=$model; }
        foreach ($products as $index=>$product) {
            $cid=(int)($product['id_customization']??0); $pid=(int)($product['id_product']??0);
            $record=$records[$cid]??null;
            if ($cid<1 || !$record || (int)$record['id_product']!==$pid) { continue; }
            try { $snapshot=json_decode($record['snapshot'],true,32,JSON_THROW_ON_ERROR); } catch (Throwable $e) { continue; }
            $modelId=$snapshot['quote']['modelId']??null;
            if (!is_string($modelId)) { continue; }
            $model=$models[$modelId]??null;
            if (!$model || $model['id']!=='watchys-7750-41mm') { continue; }
            $key=self::key($model,$snapshot['selection']??null);
            if (!$key || !$exists($key)) { continue; }
            $src=$url($key);
            $cover=is_array($product['cover']??null)?$product['cover']:[];
            $sizes=['cart_default','small_default','medium_default','home_default','large_default'];
            foreach (array_keys($cover['bySize']??[]) as $name) { $sizes[]=$name; }
            $image=['url'=>$src,'width'=>380,'height'=>494];
            $cover['bySize']=[];
            foreach (array_unique($sizes) as $name) { $cover['bySize'][$name]=$image; }
            foreach (['small','medium','large'] as $size) { $cover[$size]=$image; }
            $cover['url']=$src; $cover['legend']='Votre configuration Watchys 7750';
            // ProductLazyArray exposes these as variables; CartLazyArray.products is rewritable.
            $product['cover']=$cover; $product['default_image']=$cover; $product['images']=[$cover];
            $products[$index]=$product;
        }
        return $products;
    }
}
