<div class="space-y-6">
 <div>
 <x-ui.input id="title" name="title" type="text" label="Title" :value="old('title', $tip?->title)" required/>
 </div>

 <div class="grid gap-6 sm:grid-cols-2">
 <div>
 <x-ui.input id="species" name="species" type="text" label="Species" :value="old('species', $tip?->species)" placeholder="dog, cat, bird..."/>
 </div>

 <div>
 <x-ui.input id="category" name="category" type="text" label="Category" :value="old('category', $tip?->category)" placeholder="nutrition, training, hygiene..."/>
 </div>
 </div>

 <div>
 <x-ui.textarea id="content" name="content" rows="8" label="Tip content" :value="old('content', $tip?->content)" required/>
 </div>
</div>
